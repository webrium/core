<!DOCTYPE html>
<html>
<body>

<form action="<?= url('get/array-files') ?>" method="post" enctype="multipart/form-data">

  <div class="">
    <input type="file" name="image[]" >
  </div>

  <div style="margin-top:10px;">
    <input type="file" name="image[]" >
  </div>

  <div style="margin-top:10px;">
    <input type="file" name="image[]" >
  </div>

  <div style="margin-top:10px;">
    <input type="submit" value="Upload Files" name="submit">
  </div>

</form>

</body>
</html>
