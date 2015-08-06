
<div class="actions">
    
    <h3>Actions</h3>
    <ul>
        <li><a href="<?php echo $logout_url; ?>">Logout</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "showSelectedUsers")); ?>">Show Selected Users</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "refreshData")); ?>">Refresh</a></li>
    </ul>
</div>

<div class="view">
    <canvas id="canvas" width="800" height="1067"></canvas>
</div>

<script>

var canvas = new fabric.StaticCanvas('canvas');

fabric.Image.fromURL('http://localhost/EmotionMingleFacebook/img/arbol_01.png', function(oImg) {
  canvas.add(oImg);
});

fabric.Image.fromURL('https://fbcdn-profile-a.akamaihd.net/hprofile-ak-xfp1/v/t1.0-1/p100x100/969320_10201342091407207_1253389166_n.jpg?oh=5ca40dc81e159f7981c2f65e525c8948&oe=56478CB6&__gda__=1446551491_8e7e15b658f42b1bd1d24eb475a01fc8', function(oImg) {
    oImg.top = 640;
    oImg.left = 100;
    oImg.scale(0.8);
    canvas.add(oImg);
});


</script>