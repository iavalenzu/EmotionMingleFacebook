
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

function getLeafPosition(numLeaf)
{
    switch(numLeaf)
    {
        case 1:
            return {top: 640, left: 100};
        case 2:
            return {top: 480, left: 100};
        case 3:
            return {top: 480, left: 250};
        case 4:
            return {top: 250, left: 200};
        case 5:
            return {top: 220, left: 350};
        case 6:
            return {top: 280, left: 500};
        case 7:
            return {top: 480, left: 500};
        case 8:
            return {top: 500, left: 600};
        default:
            return {top: 10000, left: 10000};
    }
}


var canvas = new fabric.StaticCanvas('canvas');

fabric.Image.fromURL('<?php echo $this->Html->url('/img/arbol_01.png'); ?>', function(oImg) {
  canvas.add(oImg);
});

<?php if(isset($selectedUsers)): ?> 
                            
    <?php foreach($selectedUsers as $selectedUser): ?>
                            
    fabric.Image.fromURL('<?php echo $selectedUser['Friend']['pic']; ?>', function(oImg) {
        oImg.top = getLeafPosition(<?php echo $selectedUser['SelectedFriend']['leaf']; ?>).top;
        oImg.left = getLeafPosition(<?php echo $selectedUser['SelectedFriend']['leaf']; ?>).left;
        oImg.scale(0.8);
        canvas.add(oImg);
    });
                            
    <?php endforeach; ?>                 
<?php endif; ?>





</script>