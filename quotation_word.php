<?php
// Only the main document XML is changed. All branding and other ZIP parts remain intact.
function buildQuotationWord(array $project, string $template): string {
    if (!is_readable($template)) throw new RuntimeException('Word template not found.');
    $temp = tempnam(sys_get_temp_dir(), 'docubiz-');
    if ($temp === false) throw new RuntimeException('Temporary file unavailable.');
    $zip = new ZipArchive();
    $opened = false;
    try {
        if (!copy($template, $temp)) throw new RuntimeException('Cannot copy template.');
        if ($zip->open($temp) !== true) throw new RuntimeException('Invalid Word template.');
        $opened = true;
        $xml = $zip->getFromName('word/document.xml');
        $doc = new DOMDocument();
        if ($xml === false || !$doc->loadXML($xml, LIBXML_NONET)) throw new RuntimeException('Invalid document XML.');
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('w', $ns);
        // Keep the existing paragraph and run formatting; replace only its text.
        $write = function (DOMElement $p, string $value, bool $append = false) use ($doc, $xp, $ns) {
            $runs = $xp->query('./w:r', $p);
            $style = $xp->query('./w:r[1]/w:rPr', $p)->item(0) ?? $xp->query('./w:pPr/w:rPr', $p)->item(0);
            $style = $style ? $style->cloneNode(true) : null;
            if (!$append) {
                foreach (iterator_to_array($runs) as $run) $p->removeChild($run);
            } else {
                // Keep the existing bold label; add a regular-weight value.
                $style = $style ?: $doc->createElementNS($ns, 'w:rPr');
                foreach (['b', 'bCs'] as $tag) {
                    foreach (iterator_to_array($xp->query('./w:' . $tag, $style)) as $old) {
                        $style->removeChild($old);
                    }
                    $node = $doc->createElementNS($ns, 'w:' . $tag);
                    $node->setAttributeNS($ns, 'w:val', '0');
                    $style->appendChild($node);
                }
                $value = ' ' . $value;
            }
            // XML text nodes safely escape &, <, > and other markup characters.
            $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
            $run = $doc->createElementNS($ns, 'w:r');
            if ($style) $run->appendChild($style);
            foreach (preg_split('/\R/u', $value) as $i => $line) {
                if ($i) $run->appendChild($doc->createElementNS($ns, 'w:br'));
                $text = $doc->createElementNS($ns, 'w:t');
                $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
                $text->appendChild($doc->createTextNode($line));
                $run->appendChild($text);
            }
            $p->appendChild($run);
        };
        $v = static fn($key, $fallback = '—') => isset($project[$key]) && trim((string)$project[$key]) !== '' ? (string)$project[$key] : $fallback;
        $date = static fn($key) => !empty($project[$key]) ? date('F j, Y', strtotime($project[$key])) : '—';
        $time = static fn($key) => !empty($project[$key]) ? date('g:i A', strtotime($project[$key])) : '—';
        $table = '/w:document/w:body/w:tbl';
        // The revised template has TWO cells per row: each contains its label.
        // Find fields by their exact label rather than hard-coded cell numbers.
        $find = function (string $label) use ($xp) {
            $matches = [];
            foreach ($xp->query('/w:document/w:body/w:tbl/w:tr/w:tc/w:p | /w:document/w:body/w:p') as $p) {
                $text = '';
                foreach ($xp->query('.//w:t', $p) as $t) $text .= $t->textContent;
                if (trim($text) === $label) $matches[] = $p;
            }
            if (count($matches) !== 1) throw new RuntimeException('Template field missing or duplicated: ' . $label);
            return $matches[0];
        };
        $fields = [
            'QUOTE DATE:' => $date('quote_date'),
            'QUOTATION NUMBER:' => $v('quotation_number'),
            'CLIENT NAME:' => $v('client_name'),
            'COMPANY NAME:' => $v('company_name_address'),
            'CONTACT NUMBER:' => $v('contact_number'),
            'EMAIL ADDRESS:' => $v('email_address'),
            'EVENT DATE:' => $date('start_date'),
            'EVENT TYPE:' => $v('event_type'),
            'CELEBRANT NAME:' => $v('celebrant_name'),
            'NUMBER OF PAX:' => $v('guest_count'),
            'PROGRAM TIME:' => $time('event_time'),
            'FOOD SERVING TIME:' => $time('food_serving_time'),
            'MOTIF/THEME:' => $v('motif_theme'),
            'CHOSEN PACKAGE:' => $v('package_name','Customized'),
            'EVENT ADDRESS:' => $v('location'),
            'DESCRIPTION/NOTES:' => $v('description')
        ];
        // Keep the two original column widths; allow text to wrap inside cells.
        foreach ($xp->query($table . '[position() <= 2]/w:tblPr') as $props) {
            $layout = $xp->query('./w:tblLayout', $props)->item(0);
            if (!$layout) {
                $layout = $doc->createElementNS($ns, 'w:tblLayout');
                $before = $xp->query('./w:tblCellMar | ./w:tblLook', $props)->item(0);
                $props->insertBefore($layout, $before);
            }
            $layout->setAttributeNS($ns, 'w:type', 'fixed');
        }
        foreach ($fields as $label => $value) $write($find($label), $value, true);
        $write($find('Ma’am/Sir Client Name,'), 'Ma’am/Sir ' . $v('client_name') . ',');
        $menu = static function ($json) {
            $rows = json_decode((string)$json,true);
            $lines = [];
            foreach (is_array($rows)?$rows:[] as $row) {
                if (!is_array($row) || empty($row['selection'])) continue;
                $alts = $row['alternatives'] ?? [];
                if (is_string($alts)) $alts = $alts === '' ? [] : [$alts];
                $alts = is_array($alts) ? array_filter($alts,'is_string') : [];
                $lines[] = (int)($row['quantity'] ?? 1) . ' ' . $row['selection'] . ($alts ? ' or ' . implode(' or ', $alts) : '');
            }
            return $lines ? implode("\n",$lines) : 'None selected';
        };
        $write($find('Chosen food option list'),$menu($project['food_selections'] ?? '[]'));
        $write($find('Chosen drink option list'),$menu($project['drink_selections'] ?? '[]'));
        $inclusions = json_decode($project['service_inclusions'] ?? '[]',true);
        $inclusions = is_array($inclusions) ? array_filter($inclusions,'is_string') : [];
        $write($find('Inclusion 1'),$inclusions ? implode("\n",$inclusions) : 'None selected');
        // Remove the second sample inclusion row, since the first now holds the full list.
        $extra = $xp->query($table . '[4]/w:tr[3]')->item(0);
        if (!$extra) throw new RuntimeException('Inclusion placeholder missing.');
        $extra->parentNode->removeChild($extra);
        $write($find('TOTAL: PHP X.XX'), 'TOTAL: PHP ' . number_format((float)$project['quotation_total'],2,'.',','));
        if (!$zip->addFromString('word/document.xml',$doc->saveXML())) throw new RuntimeException('Cannot write Word document.');
        if (!$zip->close()) throw new RuntimeException('Cannot finalize Word document.');
        $opened = false;
        return $temp;
    } catch (Throwable $e) {
        if ($opened) $zip->close();
        if (is_file($temp)) unlink($temp);
        throw $e;
    }
}
