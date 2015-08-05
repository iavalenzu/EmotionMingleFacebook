
<div class="actions">
    
    <h3>Actions</h3>
    <ul>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "logout")); ?>">Logout</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "showSelectedUsers")); ?>">Show Selected Users</a></li>
    </ul>
</div>

<div class="view">
    
    <?php if(isset($likes)): ?>

        <form action="<?php echo $this->Html->url(array("controller" => "home", "action" => "modifySelectedUsers")); ?>" method="post">

            <fieldset>
                <legend>Selecciona a tus contactos</legend>
                <table>
                    <thead>
                        <tr>
                            <th>Selecciona</th>
                            <th>Nombre</th>
                            <th>Avatar</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($likes as $like): ?>
                        <tr>
                            <td><input type="checkbox" name="data[friend_list][]" value="<?php echo $like['Friend']['id'];?>"></td>
                            <td><?php echo $like['Friend']['name']; ?></td>
                            <td><img src="<?php echo $like['Friend']['pic']; ?>" alt="<?php echo $like['Friend']['name']; ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </fieldset>
            <input class="submit" type="submit" value="Guardar Cambios">
        </form>

    <?php else: ?>
        <h2>No hay usuarios para mostrar!!</h2>
    <?php endif; ?>
    
</div>



