
<div class="actions">
    
    <h3>Actions</h3>
    <ul>
        <li><a href="<?php echo $logout_url; ?>">Logout</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "modifySelectedUsers")); ?>">Modify Selected Users</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "refreshData")); ?>">Refresh</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "showTree")); ?>">Tree</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "account")); ?>">Cuenta</a></li>
    </ul>
</div>

<div class="view">

    <?php if(isset($selectedUsers)): ?>
    
        <fieldset>
            <legend>Contactos Seleccionados</legend>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Hoja</th>
                        <th>Avatar</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($selectedUsers as $selectedUser): ?>
                    <tr>
                        <td><?php echo $selectedUser['Friend']['name']; ?></td>
                        <td><?php echo $selectedUser['SelectedFriend']['leaf']; ?></td>
                        <td><img src="<?php echo $selectedUser['Friend']['pic']; ?>" alt="<?php echo $selectedUser['Friend']['name']; ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </fieldset>

    <?php else: ?>
        <h2>No hay usuarios para mostrar!!</h2>
    <?php endif; ?>
    
</div>

