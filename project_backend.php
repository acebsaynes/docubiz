<?php
// Included by projects.php after login and database initialization.
$projectTypes = ['Catering Quotation','Rental Quotation','Catering Contract of Agreement','Rental Service Agreement'];
$eventTypes = ['Wedding','Debut','Kiddie Birthday','Adult Birthday','Corporate','Other'];
$statuses = ['Planned','In Progress','Completed','On Hold','Cancelled'];
$foods = ['Rice','Pork','Beef','Chicken','Fish','Vegetables','Noodles/Pasta','Dessert','Soup','Appetizer'];
$drinks = ['Water','Iced Tea','Red Iced Tea','Blue Lemonade','Pink Lemonade','Cucumber Lemonade'];
$inclusions = ['Basic Backdrop','Basic Buffet Setup','Complete Catering Utensils','Round Table with Cover with Table Centerpieces and Table Numbers','Table Napkins Arranged on Every Table','Water Goblets Arranged on Every Table','Monoblock Chairs with Cover and Alternate Ribbons','Drink Station','Trained Waiters and Buffet Attendants'];
$packages = [];
foreach (['Wedding','Debut','Kiddie Birthday','Adult Birthday'] as $event) {
    $formal = in_array($event,['Wedding','Debut'],true);
    $pax = $formal ? [50,100,150,200] : ($event === 'Adult Birthday' ? [50,75,100,150] : [50,75,100,150]);
    $prices = $formal ? [57000,90000,129000,168000] : [53000,68000,85000,113000];
    foreach ($pax as $i=>$n) $packages[] = ['name'=>$event.' Package '.($i+1),'event_type'=>$event,'pax'=>$n,'price'=>$prices[$i]];
}
$projectConfig = compact('projectTypes','eventTypes','statuses','foods','drinks','inclusions','packages','isAdmin');
$projectConfig['csrf'] = $_SESSION['project_csrf'];
$projectConfig['today'] = (new DateTime('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d');
function dbProjectQuery($sql,$params=[]) {
    global $conn;
    $stmt=$conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('Database preparation failed.');
    if ($params) $stmt->bind_param(str_repeat('s',count($params)),...$params);
    if (!$stmt->execute()) throw new RuntimeException('Database query failed.');
    return $stmt;
}
function dbProjectReply($data,$code=200) {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
function dbProjectText($data,$key,$max=255) {
    $v=$data[$key]??'';
    if (!is_string($v) && !is_numeric($v)) throw new InvalidArgumentException('Invalid '.$key);
    $v=trim((string)$v);
    if (mb_strlen($v)>$max) throw new InvalidArgumentException('Too long: '.$key);
    return $v;
}
function dbProjectDate($v) {
    if ($v==='') return null;
    $d=DateTime::createFromFormat('!Y-m-d',$v);
    if (!$d || $d->format('Y-m-d')!==$v) throw new InvalidArgumentException('Invalid date.');
    return $v;
}
function dbProjectTime($v) {
    if ($v==='') return null;
    if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$v)) throw new InvalidArgumentException('Invalid time.');
    return $v;
}
function dbProjectChoices($items,$allowed) {
    if (!is_array($items) || count($items)>count($allowed)) throw new InvalidArgumentException('Invalid selections.');
    foreach ($items as $item) if (!in_array($item,$allowed,true)) throw new InvalidArgumentException('Invalid selection.');
    return array_values(array_unique($items));
}
function dbProjectMenu($rows,$allowed) {
    if (!is_array($rows) || count($rows)>50) throw new InvalidArgumentException('Invalid menu rows.');
    $out=[];
    foreach ($rows as $row) {
        if (!is_array($row)) throw new InvalidArgumentException('Invalid menu row.');
        $selection=$row['selection']??'';
        if (!in_array($selection,$allowed,true)) throw new InvalidArgumentException('Select a valid menu item.');
        $qty=filter_var($row['quantity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);
        if ($qty===false) throw new InvalidArgumentException('Invalid quantity.');
        $alt=dbProjectChoices($row['alternatives']??[],$allowed);
        if (in_array($selection,$alt,true)) throw new InvalidArgumentException('An alternative cannot equal the main selection.');
        $out[]=['selection'=>$selection,'alternatives'=>$alt,'quantity'=>$qty];
    }
    return json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}
function dbProjectGet($id) {
    return dbProjectQuery('SELECT * FROM projects WHERE id=?',[$id])->get_result()->fetch_assoc();
}
if (isset($_GET['project_detail'])) {
    try {
        $row=dbProjectGet((int)$_GET['project_detail']);
        if (!$row) dbProjectReply(['error'=>'Project not found.'],404);
        foreach (['food_selections','drink_selections','service_inclusions'] as $key) $row[$key]=json_decode($row[$key]??'[]',true)?:[];
        dbProjectReply(['project'=>$row]);
    } catch(Throwable $e) { error_log($e->getMessage()); dbProjectReply(['error'=>'Unable to load project.'],500); }
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $json = strpos($_SERVER['CONTENT_TYPE']??'','application/json')===0;
    $data=$json?json_decode(file_get_contents('php://input'),true):$_POST;
    $transaction=false;
    try {
        if (!$isAdmin) throw new DomainException('Only admins may modify projects.');
        if (!is_array($data) || !is_string($data['csrf']??null) || !hash_equals($_SESSION['project_csrf'],$data['csrf'])) throw new DomainException('Invalid security token.');
        $action=$data['action']??'';
        $id=(int)($data['id']??$data['project_id']??0);
        $old=$id?dbProjectGet($id):null;
        if ($id && !$old) throw new InvalidArgumentException('Project not found.');
        if ($action==='delete' && $old) dbProjectQuery('DELETE FROM projects WHERE id=?',[$id]);
        elseif ($action==='status' && $old) {
            $status=$data['status']??'';
            $progress=filter_var($data['progress']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100]]);
            if (!in_array($status,$statuses,true)||$progress===false) throw new InvalidArgumentException('Invalid status or progress.');
            dbProjectQuery('UPDATE projects SET status=?,progress=? WHERE id=?',[$status,$status==='Completed'?100:$progress,$id]);
        } elseif ($action==='save') {
            $type=dbProjectText($data,'project_type',50);
            if (!in_array($type,$projectTypes,true) && !($old && !$old['project_type'] && $type==='')) throw new InvalidArgumentException('Choose a project type.');
            if ($old && $old['project_type'] && $old['project_type']!==$type) throw new InvalidArgumentException('Existing document type cannot be changed.');
            $v=['project_type'=>$type?:null];
            foreach (['project_name'=>150,'client_name'=>150,'company_name_address'=>255,'contact_number'=>30,'email_address'=>150] as $k=>$max) $v[$k]=dbProjectText($data,$k,$max);
            if (!$v['project_name'] || (!$old && !$v['client_name'])) throw new InvalidArgumentException('Enter project and client names.');
            if ($v['email_address'] && !filter_var($v['email_address'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Invalid email address.');
            $catering=$type==='Catering Quotation';
            $v['event_type']=dbProjectText($data,'event_type',100)?:'Other';
            $v['start_date']=dbProjectDate(dbProjectText($data,'start_date',10));
            $v['due_date']=$catering?$v['start_date']:dbProjectDate(dbProjectText($data,'due_date',10));
            $v['event_time']=dbProjectTime(dbProjectText($data,'event_time',5));
            $v['location']=dbProjectText($data,'location');
            $v['description']=dbProjectText($data,'description',5000);
            $guest=$data['guest_count']??'';
            $v['guest_count']=($guest===''||$guest===null)?null:filter_var($guest,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100000]]);
            if ($v['guest_count']===false) throw new InvalidArgumentException('Invalid guest count.');
            if ($catering) {
                $v['food_serving_time']=dbProjectTime(dbProjectText($data,'food_serving_time',5));
                if (!in_array($v['event_type'],$eventTypes,true)||!$v['start_date']||!$v['event_time']||!$v['food_serving_time']||!$v['guest_count']||!$v['location']) throw new InvalidArgumentException('Complete required event details.');
                foreach (['celebrant_name','motif_theme'] as $k) $v[$k]=dbProjectText($data,$k,150);
                $v['package_name']=dbProjectText($data,'package_name',150)?:null;
                $pkg=null;
                foreach ($packages as $p) if ($p['name']===$v['package_name']) $pkg=$p;
                if ($v['package_name'] && (!$pkg||$pkg['event_type']!==$v['event_type'])) throw new InvalidArgumentException('Invalid package for event type.');
                $v['package_price']=$pkg['price']??null;
                $v['food_selections']=dbProjectMenu($data['food_selections']??[],$foods);
                $v['drink_selections']=dbProjectMenu($data['drink_selections']??[],$drinks);
                $v['service_inclusions']=json_encode(dbProjectChoices($data['service_inclusions']??[],$inclusions),JSON_THROW_ON_ERROR);
                $total=dbProjectText($data,'quotation_total',20);
                if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$total)||(float)$total<=0) throw new InvalidArgumentException('Enter a valid quotation total.');
                if (($data['terms_accepted']??false)!==true) throw new InvalidArgumentException('Accept the terms and conditions.');
                $v['quotation_total']=$total;
                $v['quote_date']=$old['quote_date']??$projectConfig['today'];
                $v['terms_accepted']=1;
                $v['terms_accepted_at']=(new DateTime('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
            }
            $conn->begin_transaction(); $transaction=true;
            if ($old) {
                $set=implode(',',array_map(fn($k)=>"`$k`=?",array_keys($v)));
                dbProjectQuery("UPDATE projects SET $set WHERE id=?",array_merge(array_values($v),[$id]));
            } else {
                $v['user_id']=$userId; $v['status']='Planned'; $v['progress']=0;
                $cols=implode(',',array_map(fn($k)=>"`$k`",array_keys($v)));
                $marks=implode(',',array_fill(0,count($v),'?'));
                dbProjectQuery("INSERT INTO projects ($cols) VALUES ($marks)",array_values($v));
                $id=$conn->insert_id;
            }
            if ($catering && empty($old['quotation_number'])) dbProjectQuery('UPDATE projects SET quotation_number=? WHERE id=?',[sprintf('LVQ-%s-%03d',substr($v['quote_date'],0,4),$id),$id]);
            $conn->commit(); $transaction=false;
        } else throw new InvalidArgumentException('Invalid action.');
        if ($json) dbProjectReply(['success'=>true,'id'=>$id]);
        redirectProjects();
    } catch(Throwable $e) {
        if ($transaction) $conn->rollback();
        $known=$e instanceof InvalidArgumentException||$e instanceof DomainException;
        $error=$known?$e->getMessage():'Unable to save project.';
        if (!$known) error_log($e->getMessage());
        $code=$e instanceof DomainException?403:($known?422:500);
        if ($json) dbProjectReply(['error'=>$error],$code);
        http_response_code($code);
    }
}
