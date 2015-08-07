
<div class="actions">
    
    <h3>Actions</h3>
    <ul>
        <li><a href="<?php echo $logout_url; ?>">Logout</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "modifySelectedUsers")); ?>">Modify Selected Users</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "refreshData")); ?>">Refresh</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "showTree")); ?>">Tree</a></li>
    </ul>
</div>

<div class="view">

    <?php if(isset($user)): ?>
    
        <fieldset>
            <legend>Detalles de Cuenta</legend>
                <table>
                    <tbody>
                        <tr>
                            <th></th>
                            <td>
                               <img src="<?php echo $user['User']['pic']; ?>" alt="<?php echo $user['User']['name']; ?>">
                             </td>
                        </tr>
                        <tr>
                            <th>Nombre</th>
                            <td>
                                <?php echo $user['User']['name']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Api Key</th>
                            <td>
                                <?php echo $user['User']['api_key']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Facebook Id</th>
                            <td>
                                <?php echo $user['User']['facebook_user_id']; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Emotion Mingle Tree Service Url</th>
                            <td>
                                <?php echo $this->Html->url(array("controller" => "home", "action" => "getEmotionMingleTreeData", $user['User']['api_key']), true); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
        </fieldset>

    <?php else: ?>
        <h2>No hay datos para mostrar!!</h2>
    <?php endif; ?>
    
</div>

