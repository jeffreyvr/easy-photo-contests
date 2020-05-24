<div class="wrap">

  <h2 class="nav-tab-wrapper epc-nav-tab">
    <a href="#epc-entry-section" data-epc-section="entry" class="nav-tab nav-tab-active"><?php _e( 'Entry', EPC_TEXT_DOMAIN ); ?></a>
    <a href="#epc-votes-section" data-epc-section="votes" class="nav-tab"><?php printf( __( 'Votes (%d)', EPC_TEXT_DOMAIN ), dtp_get_total_votes_entry( $entry->entry_id ) ); ?></a>
  </h2>

  <div class="epc-section epc-active-section" id="epc-entry-section" style="display: block;">
    <div class="col-container">

      <div id="col-left">

        <form method="post">

          <h2><?php _e( 'Details', EPC_TEXT_DOMAIN ); ?></h2>

          <form class="form-table">
            <tbody>
              <tr>
                <td>
                  <div class="form-field">
                    <p>
                    	<strong><?php _e( 'Entry date', EPC_TEXT_DOMAIN ); ?></strong><br>
                      <?php echo $entry->date; ?>
                    </p>
                  </div>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="form-field">
                    <p>
                    	<strong><?php _e( 'Contestant', EPC_TEXT_DOMAIN ); ?></strong><br>
                      <?php
                        $user_edit_url = ( !empty( $entry->user_id ) ? get_edit_user_link( $entry->user_id ) : null );
                        if ( !empty( $user_edit_url ) ) {
                          echo '<a href="' . $user_edit_url . '">' . epc_get_entry_contestant_name( $entry ) . '</a>';
                        } else {
                          echo epc_get_entry_contestant_name( $entry );
                        }
                      ?>
                    </p>
                  </div>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="form-field">
                  	 <p>
                       <label for="entry-name"><strong><?php _e( 'Name', EPC_TEXT_DOMAIN ); ?></strong></label><br>
                       <input name="entry_name" id="entry-name" type="text" value="<?php echo $entry->name; ?>" size="40">
                    </p>
                  </div>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="form-field">
                    <p>
                     <label for="entry-description"><strong><?php _e( 'Description', EPC_TEXT_DOMAIN ); ?></strong></label><br>
                     <textarea name="entry_description" id="entry-description"><?php echo $entry->description; ?></textarea>
                    </p>
                  </div>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="form-field">
                  	 <p>
                       <label for="entry-status"><strong><?php _e( 'Status', EPC_TEXT_DOMAIN ); ?></strong></label><br>
                       <select name="entry_status" id="entry-status">
                         <?php foreach ( array( 'approved' => __( 'Approved', EPC_TEXT_DOMAIN ), 'waiting_approval' => __( 'Awaiting approval', EPC_TEXT_DOMAIN ), 'not_approved' => __( 'Not approved', EPC_TEXT_DOMAIN ) ) as $status_value => $status_name ) { ?>
                           <option value="<?php echo $status_value; ?>" <?php selected( $entry->status, $status_value, true ); ?>><?php echo $status_name; ?></option>
                         <?php } ?>
                       </select>
                    </p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <button type="submit" name="edit_entry" value="1" class="button-primary"><?php _e( 'Save', EPC_TEXT_DOMAIN ); ?></button>

          <a href="<?php echo wp_nonce_url( epc_get_edit_entry_url( $entry->entry_id ), 'epc_delete_entry_nonce' ); ?>" class="epc-delete epc-delete-m submitdelete deletion">
            <?php _e( 'Delete', EPC_TEXT_DOMAIN ); ?>
          </a>

          <input type="hidden" name="entry_id" value="<?php echo $entry->entry_id; ?>">

          <?php wp_nonce_field( 'epc_edit_entry', 'epc_edit_entry_nonce' ); ?>

        </form>

        <p>
          <a href="<?php echo epc_get_admin_entries_url( (int) $entry->contest_id ); ?>" class="button"><?php _e( '&laquo; View all contest entries', EPC_TEXT_DOMAIN ); ?></a>
        </p>

      </div>

      <div id="col-right">
        <h2><?php _e( 'Photo', EPC_TEXT_DOMAIN ); ?></h2>

        <?php echo ( !empty( $entry->media_id ) ? '<a href="' . get_edit_post_link( $entry->media_id ) . '">' . wp_get_attachment_image( $entry->media_id, 'medium' ) . '</a>' : null ); ?>
      </div>

  </div>

  </div>

  <div class="epc-section" id="epc-votes-section">
    <div class="epc-vote-log">

      <?php if ( $votes = epc_get_entry_votes( $entry->entry_id ) ) : ?>
        <table class="form-table">
          <tr>
            <th><?php _e( 'Status', EPC_TEXT_DOMAIN ); ?></th>
            <th><?php _e( 'Date', EPC_TEXT_DOMAIN ); ?></th>
            <th><?php _e( 'Voter', EPC_TEXT_DOMAIN ); ?></th>
            <th></th>
          </tr>

          <?php foreach ( $votes as $vote ) : ?>
            <tr>
              <td>
                <?php echo $vote->status; ?>
                <?php if ( $vote->status == 'unconfirmed' ) { ?><br>
                  <a href="#epc-confirmation-url-<?php echo $vote->vote_id; ?>" data-epc-vote-id="<?php echo $vote->vote_id; ?>" class="epc-confirmation-url-toggle"><?php _e( 'Show / hide confirmation URL', EPC_TEXT_DOMAIN ); ?></a>
                  <div class="epc-confirmation-url-block epc-confirmation-url-<?php echo $vote->vote_id; ?>">
                    <pre class="epc-pre"><?php echo epc_vote_confirmation_url( $vote->token ); ?></pre>
                  </div>
                <?php } ?>
              </td>
              <td>
                <?php echo date_i18n( get_option( 'date_format' ), strtotime( $vote->date ) ); ?><br>
                <i><?php echo date_i18n( get_option( 'time_format' ), strtotime( $vote->date ) ); ?></i>
              </td>
              <td>
                <?php echo epc_get_voter( $vote ); ?>
              </td>
              <td>
                <a href="<?php echo wp_nonce_url( epc_get_delete_vote_url( $vote->entry_id, $vote->vote_id ), 'epc_delete_vote_nonce' ); ?>" class="epc-delete"><?php _e( 'Delete', EPC_TEXT_DOMAIN ); ?></a>
              </td>
            </tr>
          <?php endforeach; ?>

        </table>
      <?php else : ?>
        <p><?php _e( 'There are no votes for this entry.', EPC_TEXT_DOMAIN ); ?></p>
      <?php endif; ?>

    </div>
  </div>
