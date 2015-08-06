
<div class="actions">
    
    <h3>Actions</h3>
    <ul>
        <li><a href="<?php echo $logout_url; ?>">Logout</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "modifySelectedUsers")); ?>">Modify Selected Users</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "showSelectedUsers")); ?>">Show Selected Users</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "refreshData")); ?>">Refresh</a></li>
    </ul>
</div>

<div class="view">
    
    <?php if(isset($friend)): ?>
            <?php //debug($friend); ?>
            <fieldset>
                <legend><?php echo $friend['Friend']['name']; ?></legend>
                <table>
                    <tbody>
                        <tr>
                            <th>Avatar</th>
                            <td>
                                <img src="<?php echo $friend['Friend']['pic']; ?>" alt="<?php echo $friend['Friend']['name']; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Likes</th>
                            <td>
                                <?php echo count($friend['Like']); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Comments</th>
                            <td>
                                <?php echo count($friend['Comment']); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Tags</th>
                            <td>
                                <?php echo count($friend['Tag']); ?>
                            </td>
                        </tr>
                            
                    </tbody>
                </table>
            </fieldset>

    <?php else: ?>
        <h2>No hay datos para mostrar!!</h2>
    <?php endif; ?>
    
</div>



