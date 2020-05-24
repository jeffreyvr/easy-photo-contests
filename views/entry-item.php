<?php
/**
 * View: Entry Item
 */
?>

<div class="epc-entry-item grid-item">
  <a href="<?php echo epc_get_entry_url( $entry->entry_id, $entry->contest_id ); ?>">
    <div class="epc-entry-item-overlay-content">
      <?php printf( __( '%s votes', EPC_TEXT_DOMAIN ), dtp_get_total_votes_entry( $entry->entry_id ) ); ?><br>
      <span>
        <?php _e( 'View entry', EPC_TEXT_DOMAIN ); ?>
      </span>
    </div>
  </a>
  <div class="epc-entry-item-image" style="background-image: url(<?php echo epc_get_entry_image_url( $entry, 'epc_thumbnail' ); ?>); ?>"></div>
</div>
