
<div class="actions">
    
    <h3>Actions</h3>
    <ul>
        <li><a href="<?php echo $logout_url; ?>">Logout</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "showSelectedUsers")); ?>">Show Selected Users</a></li>
        <li><a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "refreshData")); ?>">Refresh</a></li>
    </ul>
</div>

<div class="view">
    
    <?php if(isset($friends)): ?>

        <form action="<?php echo $this->Html->url(array("controller" => "home", "action" => "modifySelectedUsers")); ?>" method="post">

            <fieldset>
                <legend>Selecciona a tus contactos</legend>
                <table>
                    <thead>
                        <tr>
                            <th>Selecciona</th>
                            <th>Hoja</th>
                            <th>Nombre</th>
                            <th>Avatar</th>
                            <th>Interacciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $count = 0; ?>    
                    <?php foreach($friends as $friend): ?>
                        <?php //debug($friend); 
                        
                            $selectedFriend = null;
                        
                            if(isset($currentSelectedFriends))
                            {
                                foreach($currentSelectedFriends as $currentSelectedFriend)
                                {
                                    if($currentSelectedFriend['SelectedFriend']['friend_id'] == $friend['Friend']['id'])
                                    {
                                        $selectedFriend = $currentSelectedFriend;
                                        break;
                                    }
                                }
                            }
                            
                        ?>
                        <tr>
                            <td><?php echo $this->Form->input(null, array('type' => 'checkbox', 'name' => "data[$count][friend_id]", 'checked' => !is_null($selectedFriend), 'hiddenField' => false, 'label' => false, 'value' => $friend['Friend']['id'])); ?></td>
                            <td>
                                <?php 
                            
                                    $leafs = array(
                                        '1' => 'Hoja 1', 
                                        '2' => 'Hoja 2', 
                                        '3' => 'Hoja 3',
                                        '4' => 'Hoja 4',
                                        '5' => 'Hoja 5',
                                        '6' => 'Hoja 6',
                                        '7' => 'Hoja 7',
                                        '8' => 'Hoja 8'
                                    );
                                    
                                    if(!is_null($selectedFriend))
                                    {
                                        echo $this->Form->input(
                                            null,
                                            array('options' => $leafs, 'name' => "data[$count][leaf]", 'default' => $selectedFriend['SelectedFriend']['leaf'], 'label' => false)
                                        );                                        
                                    }
                                    else
                                    {
                                        echo $this->Form->input(
                                            null,
                                            array('options' => $leafs, 'name' => "data[$count][leaf]", 'default' => 1, 'label' => false)
                                        );
                                    }
                                    
                                
                                ?>
                            </td>
                            <td><?php echo $friend['Friend']['name']; ?></td>
                            <td><img src="<?php echo $friend['Friend']['pic']; ?>" alt="<?php echo $friend['Friend']['name']; ?>"></td>
                            <td><?php echo $friend['Friend']['interactions']; ?></td>
                            <td class="actions">
                                <a href="<?php echo $this->Html->url(array("controller" => "home", "action" => "friendDetails", $friend['Friend']['id'])); ?>">Ver</a>
                                
                            </td>
                        </tr>
                    <?php $count++; ?>    
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



