<div class="wrap">
  <h1><?php _e( 'Entries', EPC_TEXT_DOMAIN ); ?></h1>

  <form method="get">

    <input type="hidden" name="page" value="easy_photo_contest_entries">
    <input type="hidden" name="post_type" value="easy_photo_contest">

    <div class="tablenav top">

      <?php if ( !empty( $entries ) ) { ?>
        <div class="alignleft actions bulkactions">
          <select name="action" id="bulk-action-selector-top">
            <option value="-1"><?php _e( 'Actions', EPC_TEXT_DOMAIN ); ?></option>
            <optgroup label="<?php _e( 'Status', EPC_TEXT_DOMAIN ); ?>">
              <option value="epc_approve_entries"><?php _e( "Approve", EPC_TEXT_DOMAIN ); ?></option>
              <option value="epc_not_approve_entries"><?php _e( "Not approved", EPC_TEXT_DOMAIN ); ?></option>
            </optgroup>
            <optgroup label="<?php _e( 'Permanently', EPC_TEXT_DOMAIN ); ?>">
              <option value="epc_delete_entries"><?php _e( "Delete", EPC_TEXT_DOMAIN ); ?></option>
            </optgroup>
          </select>
          <?php wp_nonce_field( 'epc_bulk_entries', 'epc_bulk_entries_nonce' ); ?>
          <button type="submit" class="button"><?php _e( 'Confirm', EPC_TEXT_DOMAIN ); ?></button>
        </div>
      <?php } ?>

      <div style="width: 100%; max-width: 400px; display: inline-block;">
        <select name="contest_id" class="epc-chosen-select">
          <option value=""><?php _e( 'Select a contest', EPC_TEXT_DOMAIN ); ?></option>
          <?php foreach ( epc_get_contests_select(true) as $id => $name ) {
            echo "<option value='$id' ".selected( $id, (!empty($_GET['contest_id']) ? (int) $_GET['contest_id'] : null), false ) . ">" . $name ."</option>";
          }?>
        </select>

        <button type="submit" class="button"><?php _e( 'Select', EPC_TEXT_DOMAIN ); ?></button>
      </div>

      <?php if ( !empty( $entries ) ) { ?>
        <p class="search-box">
          <label class="screen-reader-text" for="entry-search-input"><?php _e( 'Search entries', EPC_TEXT_DOMAIN ); ?>:</label>
          <input type="search" id="entry-search-input" name="search" value="<?php echo (!empty( $_GET['search'] ) ? esc_attr( $_GET['search'] ) : null ); ?>">
          <input type="submit" tabindex="1" id="search-submit" class="button" value="<?php _e( 'Search entries', EPC_TEXT_DOMAIN ); ?>">
        </p>
      <?php } ?>

    </div>

    <?php if ( !empty( $entries ) ) : ?>
      <table class="wp-list-table widefat fixed striped">
        <thead>
          <tr>
            <th class="check-column">
              <label class="screen-reader-text"><?php _e( 'Select all', EPC_TEXT_DOMAIN ); ?></label>
              <input class="epc-check-all-entries" type="checkbox">
            </th>
            <th><?php _e( 'Name', EPC_TEXT_DOMAIN ); ?></th>
            <th><?php _e( 'Contestant', EPC_TEXT_DOMAIN ); ?></th>
            <th><?php _e( 'Submit date', EPC_TEXT_DOMAIN ); ?></th>
            <th><?php _e( 'Status', EPC_TEXT_DOMAIN ); ?></th>
            <th><?php _e( 'Votes', EPC_TEXT_DOMAIN ); ?></th>
            <th><?php _e( 'Photo', EPC_TEXT_DOMAIN ); ?></th>
          </tr>
        </thead>
          <tbody>
        <?php foreach ( $entries as $entry ) : ?>
            <tr>
              <th class="check-column">
                <label class="screen-reader-text"><?php _e( 'Select entry', EPC_TEXT_DOMAIN ); ?></label>
                <input type="checkbox" class="epc-entry-checkbox" name="entry_id[]" value="<?php echo $entry->entry_id; ?>">
              </th>
              <td class="title has-row-actions column-primary">
                <strong><a href="<?php echo epc_get_edit_entry_url( $entry->entry_id ); ?>"><?php echo $entry->name; ?></a></strong>
                <div class="row-actions">
                  <span class="view"><a href="<?php echo epc_get_entry_url( $entry->entry_id, $entry->contest_id ); ?>"><?php _e( 'View', EPC_TEXT_DOMAIN ); ?></a> | </span>
                  <span class="edit"><a href="<?php echo epc_get_edit_entry_url( $entry->entry_id ); ?>"><?php _e( 'Edit', EPC_TEXT_DOMAIN ); ?></a> | </span>
                  <span class="trash"><a href="<?php echo wp_nonce_url( epc_get_edit_entry_url( $entry->entry_id ), 'epc_delete_entry_nonce' ); ?>" class="epc-delete"><?php _e( 'Delete', EPC_TEXT_DOMAIN ); ?></a></span>
                </div>
              </td>
              <td class="author">
                <?php
                  $user_edit_url = ( !empty( $entry->user_id ) ? get_edit_user_link( $entry->user_id ) : null );
                  if ( !empty( $user_edit_url ) ) {
                    echo '<a href="' . $user_edit_url . '">' . epc_get_entry_contestant_name( $entry ) . '</a>';
                  } else {
                    echo epc_get_entry_contestant_name( $entry );
                  }
                ?>
              </td>
              <td class="date"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $entry->date ) ); ?></td>
              <td class="status"><?php echo epc_get_entry_status_readable( $entry->status ); ?></td>
              <td class="votes"><?php echo dtp_get_total_votes_entry( $entry->entry_id ); ?></td>
              <td><?php echo ( !empty( $entry->media_id ) ? '<a href="' . get_edit_post_link( $entry->media_id ) . '">' . wp_get_attachment_image( $entry->media_id, array( 50, 50 ) ) . '</a>' : null ); ?></td>
            </tr>
        <?php endforeach; ?>
      </tbody>

    </table>

    <?php
      global $wpdb;

      echo paginate_links( array(
      	'base' => epc_get_admin_entries_url( (int) $_GET['contest_id'] ) . '&%_%',
      	'format' => 'paging=%#%',
      	'current' => max( 1, ( ! empty( $_GET['paging'] ) ? (int) $_GET['paging'] : 0 ) ),
        'total' => ceil( epc_get_total_entries( (int) $_GET['contest_id'], ( !empty( $_GET['search'] ) ? esc_attr( $_GET['search'] ) : null ) ) / epc_get_entries_per_page() )
      ) );
    ?>

    <?php else : ?>

        <p><?php _e( 'No contest was selected or no entries are found.', EPC_TEXT_DOMAIN ); ?>

    <?php endif; ?>

  </form>

</div>
