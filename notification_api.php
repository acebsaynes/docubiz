<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
date_default_timezone_set('Asia/Manila');
function ntReply($data,$status=200){http_response_code($status);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
$uid=(int)($_SESSION['user_id']??0);
if(!$uid) ntReply(['error'=>'Please sign in.'],401);
require __DIR__.'/db.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
function ntQuery($sql,$types='',...$args){global $conn;$s=$conn->prepare($sql);if($types)$s->bind_param($types,...$args);$s->execute();return $s;}
function ntRows($sql,$types='',...$args){$s=ntQuery($sql,$types,...$args);$r=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $r;}
function ntKey(...$parts){return hash('sha256',json_encode($parts,JSON_INVALID_UTF8_SUBSTITUTE));}
function ntDate($date){return date('M j, Y',strtotime($date));}
function ntCalendar($date){return 'calendar.php?month='.date('n',strtotime($date)).'&year='.date('Y',strtotime($date));}
function ntItems($uid){
 $items=[];
 $today=new DateTimeImmutable('today');$end=$today->modify('+7 days')->format('Y-m-d');$day=$today->format('Y-m-d');
 // SQL timestamps use the database clock for activity ages; reminders use the Manila calendar date.
 $add=function($key,$kind,$creator,$description,$url,$age=0,$legacy=false)use(&$items){$items[$key]=['id'=>$key,'kind'=>$kind,'creator'=>$creator,'description'=>$description,'url'=>$url,'age_seconds'=>max(0,(int)$age),'legacy_read'=>$legacy];};
 $visible="p.creator_id<>? AND (p.kind='poll' OR (p.kind='file' AND EXISTS(SELECT 1 FROM communication_files f WHERE f.post_id=p.id))) AND (p.all_employees=1 OR EXISTS(SELECT 1 FROM communication_recipients r WHERE r.post_id=p.id AND r.user_id=?))";
 foreach(ntRows("SELECT p.id,p.kind,p.body,u.full_name AS creator,TIMESTAMPDIFF(SECOND,p.created_at,CURRENT_TIMESTAMP) AS age_seconds,(n.post_id IS NOT NULL) AS was_read FROM communication_posts p JOIN users u ON u.id=p.creator_id LEFT JOIN notification_reads n ON n.post_id=p.id AND n.user_id=? WHERE $visible",'iii',$uid,$uid,$uid) as $r){
  $add(ntKey('post',$r['id']),$r['kind'],$r['creator'],$r['kind']==='file'?'Shared a file with you.':'Created a poll about “'.$r['body'].'”',$r['kind']==='file'?'documents.php':'dashboard.php',$r['age_seconds'],(bool)$r['was_read']);
 }
 // Shared calendar and project records are already visible to every authenticated account.
 foreach(ntRows("SELECT e.*,u.full_name AS creator,TIMESTAMPDIFF(SECOND,COALESCE(e.updated_at,e.created_at),CURRENT_TIMESTAMP) AS age_seconds FROM events e LEFT JOIN users u ON u.id=e.created_by") as $r){
  if($r['updated_at']||(int)$r['created_by']!==$uid){$add(ntKey('event',$r['id'],$r['updated_at']??$r['created_at']),'event',$r['updated_at']?'Calendar update':($r['creator']?:'Calendar'),($r['updated_at']?'Updated event: ':'Scheduled event: ').$r['event_name'].' — '.ntDate($r['event_date']),ntCalendar($r['event_date']),$r['age_seconds']);}
  if($r['event_date']>=$day&&$r['event_date']<=$end){$days=(int)$today->diff(new DateTimeImmutable($r['event_date']))->days;$stage=$days===0?'today':($days===1?'tomorrow':'within7');$add(ntKey('event-reminder',$r['id'],$r['event_date'],$stage),'reminder','Calendar reminder',$r['event_name'].' — '.($days===0?'Today':($days===1?'Tomorrow':ntDate($r['event_date']))),ntCalendar($r['event_date']));}
 }
 foreach(ntRows("SELECT id,project_name,status,progress,user_id,created_at,updated_at,CASE WHEN project_type='Rental Quotation' THEN COALESCE(start_date,due_date) ELSE COALESCE(due_date,start_date) END AS schedule_date,TIMESTAMPDIFF(SECOND,updated_at,CURRENT_TIMESTAMP) AS age_seconds FROM projects") as $r){
  $changed=$r['updated_at']!==$r['created_at'];
  // Creator is not necessarily the most recent editor; never misattribute an edit.
  $add(ntKey('project',$r['id'],$r['updated_at'],$r['status'],$r['progress']),'project','Projects',($changed?'Project updated: ':'Project: ').$r['project_name'].' — '.$r['status'].' ('.$r['progress'].'%)','projects.php',$r['age_seconds']);
  $date=$r['schedule_date'];if($date&&$date>=$day&&$date<=$end&&!in_array($r['status'],['Cancelled','Completed'],true)){$days=(int)$today->diff(new DateTimeImmutable($date))->days;$stage=$days===0?'today':($days===1?'tomorrow':'within7');$add(ntKey('project-reminder',$r['id'],$date,$stage),'reminder','Project reminder',$r['project_name'].' — '.($days===0?'Today':($days===1?'Tomorrow':ntDate($date))),ntCalendar($date));}
 }
 // Tasks are private, even for administrators viewing their own notifications.
 foreach(ntRows('SELECT id,task_title,deadline FROM tasks WHERE user_id=? AND deadline<=?','is',$uid,$end) as $r){$date=$r['deadline'];$stage=$date<$day?'overdue':($date===$day?'today':($date===$today->modify('+1 day')->format('Y-m-d')?'tomorrow':'within7'));$labels=['overdue'=>'Overdue task','today'=>'Task due today','tomorrow'=>'Task due tomorrow','within7'=>'Upcoming task'];$add(ntKey('task',$r['id'],$date,$stage),'task','My tasks',$labels[$stage].': '.$r['task_title'].' — '.ntDate($date),'dashboard.php');}
 // Inventory is shared in the existing Inventory module. Threshold is a simple configurable default.
 $lowStock=5;
 foreach(ntRows('SELECT id,product_name,quantity FROM inventory_items WHERE quantity<=?','i',$lowStock) as $r){$add(ntKey('stock',$r['id'],$r['quantity']),'inventory','Inventory',($r['quantity']==0?'Out of stock: ':'Low stock: ').$r['product_name'].' ('.$r['quantity'].' remaining)','inventory.php');}
 $reads=[];foreach(ntRows('SELECT notification_key FROM notification_activity_reads WHERE user_id=?','i',$uid) as $r)$reads[$r['notification_key']]=true;
 foreach($items as $key=>&$item){$item['unread']=!isset($reads[$key])&&!$item['legacy_read'];unset($item['legacy_read']);}unset($item);
 uasort($items,function($a,$b){return $a['age_seconds']<=>$b['age_seconds']?:strcmp($a['id'],$b['id']);});
 return $items;
}
try{
 if(!ntRows('SELECT id FROM users WHERE id=?','i',$uid))ntReply(['error'=>'Please sign in.'],401);
 if(empty($_SESSION['notification_csrf']))$_SESSION['notification_csrf']=bin2hex(random_bytes(32));
 $method=$_SERVER['REQUEST_METHOD'];
 if($method!=='GET'&&$method!=='POST')ntReply(['error'=>'Method not allowed.'],405);
 if($method==='POST'&&(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['notification_csrf'],$_POST['csrf'])))ntReply(['error'=>'Refresh the page and try again.'],403);
 $items=ntItems($uid);
 if($method==='POST'){
  $action=$_POST['action']??'';
  if($action==='read'){$id=$_POST['id']??'';if(!is_string($id)||!isset($items[$id]))ntReply(['error'=>'Notification not found.'],404);$keys=[$id];}
  elseif($action==='read_all'){$keys=array_keys(array_filter($items,fn($i)=>$i['unread']));}
  else ntReply(['error'=>'Unknown action.'],400);
  $conn->begin_transaction();try{foreach($keys as $key){ntQuery('INSERT INTO notification_activity_reads(user_id,notification_key) VALUES(?,?) ON DUPLICATE KEY UPDATE read_at=notification_activity_reads.read_at','is',$uid,$key)->close();$items[$key]['unread']=false;}$conn->commit();}catch(Throwable $e){$conn->rollback();throw $e;}
 }
 ntReply(['items'=>array_values($items),'unread'=>count(array_filter($items,fn($i)=>$i['unread'])),'csrf'=>$_SESSION['notification_csrf']]);
}catch(Throwable $e){error_log($e->getMessage());ntReply(['error'=>'Notifications unavailable. Check the notification SQL migration.'],500);}
