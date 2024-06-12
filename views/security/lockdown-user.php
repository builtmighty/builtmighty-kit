<div class="lockdown-ips" id="builtmighty-lockdown-ips">
    <h2>Allowed IPs</h2>
    <p>Current allowed IPs for the user.</p>
    <div class="builtmighty-lockdown-add">
        <input type="text" name="user_ip" placeholder="Add IP Address" />
        <button name="add_ip" class="button button-primary">+</button>
    </div><?php

    // Check if user has IPs.
    if( ! empty( $ips ) ) { 
        
        // IP Addresses. ?>
        <table>
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th></th>
                </tr>
            </thead>
            <tbody><?php

                // Loop through IPs.
                foreach( $ips as $id => $ip ) {

                    // Output. ?>
                    <tr>
                        <td><?php echo $ip; ?></td>
                        <td><button name="remove_ip" class="button button-primary" value="<?php echo $id; ?>">×</button></td>
                    </tr><?php

                } ?>

            </tbody>
        </table><?php

    } else {

        // No allowed IPs for the user.

    } ?>

</div>