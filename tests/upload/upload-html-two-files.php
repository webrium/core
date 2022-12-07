<!DOCTYPE html>
<html>
<body>

<form action="<?= url('get/two-files') ?>" method="post" enctype="multipart/form-data">

  <div class="">
    <input type="file" name="image-1" >
  </div>

  <div style="margin-top:10px;">
    <input type="file" name="image-2" >
  </div>

  <div style="margin-top:10px;">
    <input type="submit" value="Upload Two Files" name="submit">
  </div>

</form>

</body>
</html>
