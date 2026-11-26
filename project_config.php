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
