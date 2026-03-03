<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
$user = backend_user();
$pdo = get_pdo();

$errors=[];$success=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!verify_csrf_token($_POST['csrf_token']??null)) {$errors[]='Invalid CSRF token.';} else {
    try {
      $stmt=$pdo->prepare('INSERT INTO study_materials (course_id,title,file_path,material_type,uploaded_at) VALUES (?, ?, ?, ?, NOW())');
      $stmt->execute([(int)$_POST['course_id'],trim((string)$_POST['title']),trim((string)$_POST['file_path']),(string)$_POST['material_type']]);
      $id=(int)$pdo->lastInsertId();
      $stmt=$pdo->prepare('INSERT INTO content_metadata (entity_type,entity_id,language,visibility,version_label,license_type,tags_json) VALUES ("material", ?, ?, ?, ?, ?, ?)');
      $stmt->execute([$id,trim((string)$_POST['language'])?:'en',(string)$_POST['visibility'],trim((string)$_POST['version_label'])?:null,trim((string)$_POST['license_type'])?:null,trim((string)$_POST['tags_json'])?:null]);
      $success='Material saved.';
    } catch (Throwable $e) {$errors[]='Save failed: '.$e->getMessage();}
  }
}
$courses=$pdo->query('SELECT id,title FROM courses ORDER BY id DESC LIMIT 200')->fetchAll();
$rows=$pdo->query("SELECT sm.id,c.title AS course_title,sm.title,sm.material_type FROM study_materials sm JOIN courses c ON c.id=sm.course_id WHERE sm.material_type IN ('pdf','doc','ppt') ORDER BY sm.id DESC LIMIT 150")->fetchAll();
require_once dirname(__DIR__).'/includes_header.php';
?>
<div class="eq-page-head"><h2>Study Materials Backend</h2><p class="subtitle">Dedicated material management page.</p></div>
<?php require __DIR__.'/nav.php'; ?>
<?php if($success):?><div class="alert alert-success"><?php echo htmlspecialchars($success);?></div><?php endif;?>
<?php if($errors):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e):?><li><?php echo htmlspecialchars($e);?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="row g-3"><div class="col-md-5"><form method="post" class="card p-3"><?php echo csrf_field();?><h6>Add Material</h6><select class="form-select mb-2" name="course_id" required><?php foreach($courses as $c):?><option value="<?php echo (int)$c['id'];?>"><?php echo htmlspecialchars($c['title']);?></option><?php endforeach;?></select><input class="form-control mb-2" name="title" required><input class="form-control mb-2" name="file_path" required><select class="form-select mb-2" name="material_type"><option value="pdf">pdf</option><option value="doc">doc</option><option value="ppt">ppt</option></select><div class="row g-2 mb-2"><div class="col"><input class="form-control" name="language" value="en"></div><div class="col"><select class="form-select" name="visibility"><option value="public">public</option><option value="enrolled_only">enrolled_only</option><option value="private">private</option></select></div></div><input class="form-control mb-2" name="version_label" placeholder="v1"><input class="form-control mb-2" name="license_type" placeholder="license"><input class="form-control mb-2" name="tags_json" placeholder='["worksheet"]'><button class="btn btn-primary btn-sm">Save</button></form></div><div class="col-md-7"><div class="card p-3"><h6>Recent Materials</h6><ul class="small mb-0"><?php foreach($rows as $r):?><li>#<?php echo (int)$r['id'];?> <?php echo htmlspecialchars($r['course_title'].' -> '.$r['title'].' ['.$r['material_type'].']');?></li><?php endforeach;?></ul></div></div></div>
<?php require_once dirname(__DIR__).'/includes_footer.php';
