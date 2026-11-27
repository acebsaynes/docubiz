<?php
function rentalCatalog() { return json_decode('{"Tables": [{"name": "Table rectangle 4-6 seater", "rate": 80, "unit": "pc"}, {"name": "Table round 8 seater", "rate": 150, "unit": "pc"}, {"name": "Table round 10 seater", "rate": 200, "unit": "pc"}, {"name": "Long Table", "rate": 150, "unit": "pc"}, {"name": "Kiddie table", "rate": 50, "unit": "pc"}, {"name": "Cocktail table", "rate": 200, "unit": "pc"}, {"name": "Buffet pleated table 8ft", "rate": 500, "unit": "set"}, {"name": "Buffet pleated table 4ft", "rate": 300, "unit": "set"}], "Chairs": [{"name": "Monoblock chairs without cover", "rate": 12, "unit": "pc"}, {"name": "Monoblock chairs with cover", "rate": 25, "unit": "pc"}, {"name": "Monoblock chairs with cover and ribbon", "rate": 30, "unit": "pc"}, {"name": "Kiddie Chair", "rate": 10, "unit": "pc"}, {"name": "Kiddie Chair with cover", "rate": 20, "unit": "pc"}, {"name": "Tiffany Chairs", "rate": 70, "threshold": 100, "discount": 60, "unit": "pc"}, {"name": "Foldable white chair", "rate": 110, "threshold": 101, "discount": 100, "unit": "pc"}], "Tents": [{"name": "Tent 10X10ft", "rate": 600, "unit": "pc"}, {"name": "Tent 12x12ft", "rate": 800, "unit": "pc"}, {"name": "Tent 12x24ft", "rate": 2000, "unit": "pc"}, {"name": "Tent 12x24ft with ceiling", "rate": 3000, "unit": "set"}, {"name": "Tent 16x24ft", "rate": 2500, "unit": "pc"}, {"name": "Tent 16x24ft with ceiling", "rate": 3800, "unit": "set"}, {"name": "Tent 32X32ft", "rate": 20000, "unit": "pc"}, {"name": "Tent 32X32ft with ceiling", "rate": 24000, "unit": "pc"}], "Entrance/Backdrop Frame": [{"name": "Entrance Metal Arch Frame", "rate": 500, "unit": "pc"}, {"name": "Entrance Metal Square Frame", "rate": 500, "unit": "pc"}, {"name": "Entrance Metal Circular Frame", "rate": 500, "unit": "pc"}, {"name": "Metal Round Backdrop", "rate": 500, "unit": "pc"}, {"name": "Metal Frame Arch with artificial Flowers", "rate": 3000, "unit": "pc"}, {"name": "Rustic twigs arch", "rate": 400, "unit": "pc"}], "Catering Equipments": [{"name": "Chafing dish round", "rate": 120, "unit": "set"}, {"name": "Chafing dish full size rectangular", "rate": 150, "unit": "set"}, {"name": "Chafing dish roll-top", "rate": 250, "unit": "set"}, {"name": "Food lamp", "rate": 80, "unit": "pc"}, {"name": "Coffee Percolator", "rate": 300, "unit": "pc"}, {"name": "10” Dinner plates", "rate": 8, "unit": "pc"}, {"name": "8” Salad plate", "rate": 6, "unit": "pc"}, {"name": "Drinking glass", "rate": 4, "unit": "pc"}, {"name": "Fork & spoon", "rate": 6, "unit": "pc"}, {"name": "Dessert plate-saucer", "rate": 5, "unit": "pc"}, {"name": "Water goblet", "rate": 7, "unit": "pc"}, {"name": "Chocolate fountain machine", "rate": 300, "unit": "pc"}, {"name": "Table napkin", "rate": 10, "unit": "pc"}, {"name": "Long linen", "rate": 150, "unit": "pc"}, {"name": "Table topper", "rate": 25, "unit": "pc"}], "Wedding/Debutant Couches": [{"name": "Love Chair", "rate": 2500, "unit": "pc"}, {"name": "Royal Divan Couch", "rate": 2500, "unit": "pc"}, {"name": "Victorian Wedding Couch (white and gold)", "rate": 2500, "unit": "pc"}, {"name": "Cleopatra couch with back (white & silver)", "rate": 2500, "unit": "pc"}, {"name": "Cleopatra Couch w/o back (cream leather with gold)", "rate": 2500, "unit": "pc"}, {"name": "Hi-back pure white leather Couch", "rate": 2000, "unit": "pc"}, {"name": "Hi-Back all white queen accent chair", "rate": 2000, "unit": "pc"}, {"name": "Leather beige couch", "rate": 1500, "unit": "pc"}, {"name": "Red accent chair", "rate": 1500, "unit": "pc"}, {"name": "Kiddie solo couch", "rate": 1000, "unit": "pc"}]}', true, 512, JSON_THROW_ON_ERROR); }
function rentalCalculation(array $rows, string $delivery, string $pickup, string $fee = '0'): array {
    $zone = new DateTimeZone('Asia/Manila');
    $a = new DateTimeImmutable($delivery, $zone); $b = new DateTimeImmutable($pickup, $zone);
    if ($b < $a) throw new InvalidArgumentException('Pull-out date cannot precede delivery.');
    $days = max(1, (int)ceil(($b->getTimestamp()-$a->getTimestamp())/86400));
    if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $fee)) throw new InvalidArgumentException('Invalid delivery fee.');
    $feeCents = (int)round((float)$fee * 100);
    $catalog = rentalCatalog(); $lookup = [];
    foreach ($catalog as $category => $items) foreach ($items as $item) $lookup[$item['name']] = $item + ['category'=>$category];
    if (!$rows || count($rows)>100) throw new InvalidArgumentException('Select rental items.');
    $quantities=[];
    foreach ($rows as $row) {
        if (!is_array($row) || !is_string($row['name']??null) || !isset($lookup[$row['name']])) throw new InvalidArgumentException('Invalid rental item.');
        $qty=filter_var($row['quantity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);
        if ($qty===false) throw new InvalidArgumentException('Invalid rental quantity.');
        $name=$row['name']; $quantities[$name]=($quantities[$name]??0)+$qty;
        if ($quantities[$name]>100000) throw new InvalidArgumentException('Rental quantity exceeds limit.');
    }
    $lines=[]; $subtotal=0;
    foreach ($quantities as $name=>$qty) {
        $i=$lookup[$name]; $rate=isset($i['threshold']) && $qty >= $i['threshold'] ? $i['discount'] : $i['rate'];
        $total=$qty*$rate; $subtotal+=$total;
        $lines[]=['name'=>$name,'category'=>$i['category'],'unit'=>$i['unit'],'quantity'=>$qty,'rate'=>$rate,'total'=>$total];
    }
    $totalCents = $subtotal * $days * 100 + $feeCents;
    if ($totalCents>999999999999) throw new InvalidArgumentException('Rental total exceeds database limit.');
    return ['items'=>$lines,'days'=>$days,'subtotal'=>$subtotal,'total'=>$totalCents/100,'delivery_fee'=>$feeCents/100];
}
