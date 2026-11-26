<?php
session_start();
require __DIR__.'/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
function reply($data,$status=200){http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function q($sql,$types='',...$args){global $conn;$s=$conn->prepare($sql);if($types)$s->bind_param($types,...$args);$s->execute();return $s;}
function rows($sql,$types='',...$args){$s=q($sql,$types,...$args);$r=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $r;}
function fail($msg,$status=422){reply(['error'=>$msg],$status);}
function allowedPost($id,$uid){return rows("SELECT p.* FROM communication_posts p WHERE p.id=? AND p.kind IN ('poll','file') AND (p.all_employees=1 OR p.creator_id=? OR EXISTS(SELECT 1 FROM communication_recipients r WHERE r.post_id=p.id AND r.user_id=?))",'iii',$id,$uid,$uid)[0]??null;}
$uid=(int)($_SESSION['user_id']??0);
$tx=false;
try{
 if(!$uid||!($account=rows('SELECT id,role FROM users WHERE id=?','i',$uid)[0]??null))fail('Sign in first.',401);
 $isAdmin=$account['role']==='admin';
 if(empty($_SESSION['communication_csrf']))$_SESSION['communication_csrf']=bin2hex(random_bytes(32));
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $action=$_GET['action']??'feed';
  if($action==='config')reply(['csrf'=>$_SESSION['communication_csrf'],'users'=>rows('SELECT id,full_name FROM users ORDER BY full_name,id')]);
  if($action==='download'){
   $id=(int)($_GET['id']??0);
   $f=rows('SELECT * FROM communication_files WHERE id=?','i',$id)[0]??null;
   if(!$f||!allowedPost((int)$f['post_id'],$uid))fail('File not found.',404);
   header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
   header("Content-Disposition: attachment; filename=\"download\"; filename*=UTF-8''".rawurlencode($f['filename']));header('Content-Length: '.strlen($f['contents']));echo $f['contents'];exit;
  }
  if($action!=='feed')fail('Unknown action.',400);
  $documents=($_GET['scope']??'')==='documents';
  $posts=rows("SELECT p.id,p.creator_id,p.kind,p.body,p.all_employees,p.created_at,u.full_name AS creator, (p.all_employees=1 OR p.creator_id=? OR EXISTS(SELECT 1 FROM communication_recipients ar WHERE ar.post_id=p.id AND ar.user_id=?)) AS can_download FROM communication_posts p JOIN users u ON u.id=p.creator_id WHERE p.kind IN ('poll','file') AND ((p.all_employees=1 OR p.creator_id=? OR EXISTS(SELECT 1 FROM communication_recipients r WHERE r.post_id=p.id AND r.user_id=?)) OR (?=1 AND p.kind='file')) ORDER BY p.created_at DESC,p.id DESC",'iiiii',$uid,$uid,$uid,$uid,(int)($documents&&$isAdmin));
  foreach($posts as &$p){$id=(int)$p['id'];$p['can_delete']=$p['kind']==='file'&&($isAdmin||(int)$p['creator_id']===$uid);$p['recipients']=$p['all_employees']?'All employees':implode(', ',array_column(rows('SELECT u.full_name FROM communication_recipients r JOIN users u ON u.id=r.user_id WHERE r.post_id=? ORDER BY u.full_name','i',$id),'full_name'));
   $p['files']=rows('SELECT id,filename,mime,size_bytes FROM communication_files WHERE post_id=?','i',$id);
   if($p['kind']==='poll'){$p['options']=rows('SELECT o.id,o.label,COUNT(v.user_id) AS votes FROM communication_options o LEFT JOIN communication_votes v ON v.post_id=o.post_id AND v.option_id=o.id WHERE o.post_id=? GROUP BY o.id,o.label ORDER BY o.id','i',$id);$p['my_vote']=rows('SELECT option_id FROM communication_votes WHERE post_id=? AND user_id=?','ii',$id,$uid)[0]['option_id']??null;}
  }unset($p);reply(['posts'=>$posts]);
 }
 if($_SERVER['REQUEST_METHOD']!=='POST')fail('Method not allowed.',405);
 if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['communication_csrf'],$_POST['csrf']))fail('Invalid security token. Refresh the page.',403);
 $action=$_POST['action']??'';
 if($action==='delete_files'){
  $ids=json_decode($_POST['file_ids']??'[]',true);
  if(!is_array($ids)||!count($ids)||count($ids)>500)fail('Select 1–500 files.');
  foreach($ids as $id)if(!is_int($id)||$id<1)fail('Invalid file ID.');
  $ids=array_values(array_unique($ids));sort($ids,SORT_NUMERIC);
  $conn->begin_transaction();$tx=true;
  foreach($ids as $id){
   $f=rows("SELECT f.id,p.creator_id,p.kind FROM communication_files f JOIN communication_posts p ON p.id=f.post_id WHERE f.id=? FOR UPDATE",'i',$id)[0]??null;
   if(!$f||$f['kind']!=='file'||(!$isAdmin&&(int)$f['creator_id']!==$uid)){
    $conn->rollback();$tx=false;fail('One or more files no longer exist or you do not have permission to delete them. Nothing was deleted.',403);
   }
   $stmt=q("DELETE f FROM communication_files f JOIN communication_posts p ON p.id=f.post_id WHERE f.id=? AND p.kind='file' AND (?=1 OR p.creator_id=?)",'iii',$id,(int)$isAdmin,$uid);
   if($stmt->affected_rows!==1){$stmt->close();throw new RuntimeException('File deletion failed.');}$stmt->close();
  }
  $conn->commit();$tx=false;reply(['success'=>true,'deleted'=>count($ids)]);
 }

 if($action==='vote'){
  $id=(int)($_POST['post_id']??0);$option=(int)($_POST['option_id']??0);$p=allowedPost($id,$uid);
  if(!$p||$p['kind']!=='poll'||!rows('SELECT id FROM communication_options WHERE post_id=? AND id=?','ii',$id,$option))fail('Invalid poll option.');
  q('INSERT INTO communication_votes(post_id,user_id,option_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE option_id=VALUES(option_id)','iii',$id,$uid,$option)->close();reply(['success'=>true]);
 }
 if($action!=='create')fail('Unknown action.',400);
 $kind=$_POST['kind']??'';$body=trim((string)($_POST['body']??''));
 if(!in_array($kind,['poll','file'],true))fail('Messages are preview-only. Only polls and file uploads are accepted.');
 if($kind==='file')$body='';
 $all=$kind==='poll'||($_POST['all_employees']??'')==='1';
 $recipients=json_decode($_POST['recipients']??'[]',true);
 if(!is_array($recipients)||count($recipients)>500)fail('Invalid recipients.');
 $recipients=array_unique(array_map('intval',$recipients));
 if(!$all&&!$recipients)fail('Select at least one recipient.');
 if(!$all)foreach($recipients as $r)if(!rows('SELECT id FROM users WHERE id=?','i',$r))fail('Unknown recipient.');
 $options=json_decode($_POST['options']??'[]',true);
 if($kind==='poll'){
  if(!$body||strlen($body)>500||!is_array($options)||count($options)<2||count($options)>10)fail('Enter a question and 2–10 options.');
  foreach($options as &$o){if(!is_string($o)||!($o=trim($o))||strlen($o)>200)fail('Invalid poll option.');}unset($o);
  if(count(array_unique($options))!==count($options))fail('Poll options must be different.');
 }
 $files=[];$total=0;
 $allowed=['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','png','jpg','jpeg','gif','webp','mp4'];
 if(isset($_FILES['files'])){
  $u=$_FILES['files'];if(!is_array($u['name'])||count($u['name'])>5||$kind==='poll')fail('Select up to five attachments.');
  foreach($u['name'] as $i=>$name){if($u['error'][$i]===UPLOAD_ERR_NO_FILE)continue;
   if($u['error'][$i]!==UPLOAD_ERR_OK||!is_uploaded_file($u['tmp_name'][$i]))fail('Upload failed. Check PHP upload limits.');
   $name=basename(str_replace('\\','/',$name));$size=(int)$u['size'][$i];$total+=$size;
   if(!$size||$size>5*1024*1024||$total>10*1024*1024||strlen($name)>255||!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),$allowed,true))fail('Unsupported file or size limit exceeded (5 MB each, 10 MB total).');
   $bytes=file_get_contents($u['tmp_name'][$i]);if($bytes===false)fail('Cannot read upload.');
   $files[]=[$name,'application/octet-stream',$size,$bytes];
  }
 }
 if($kind==='file'&&!$files)fail('Choose a file.');
 $conn->begin_transaction();$tx=true;
 q('INSERT INTO communication_posts(creator_id,kind,body,all_employees) VALUES(?,?,?,?)','issi',$uid,$kind,$body,(int)$all)->close();$id=$conn->insert_id;
 if(!$all)foreach($recipients as $r)q('INSERT INTO communication_recipients(post_id,user_id) VALUES(?,?)','ii',$id,$r)->close();
 if($kind==='poll')foreach($options as $o)q('INSERT INTO communication_options(post_id,label) VALUES(?,?)','is',$id,$o)->close();
 foreach($files as [$name,$mime,$size,$bytes]){$s=$conn->prepare('INSERT INTO communication_files(post_id,filename,mime,size_bytes,contents) VALUES(?,?,?,?,?)');$blob=null;$s->bind_param('issib',$id,$name,$mime,$size,$blob);foreach(str_split($bytes,65536) as $chunk)$s->send_long_data(4,$chunk);$s->execute();$s->close();}
 $conn->commit();$tx=false;reply(['success'=>true]);
}catch(Throwable $e){if($tx)$conn->rollback();error_log($e->getMessage());reply(['error'=>'Unable to complete request. Check the communication SQL migration and PHP upload limits.'],500);}
