<?php
/**
 * View: Entry
 */
?>

<?php if ( $entry->status == 'not_approved' && current_user_can( 'edit_others_posts' ) ) : ?>
  <div class="epc-msg"><?php _e( 'This entry has not been approved yet. This entry is not visible for visitors.', EPC_TEXT_DOMAIN ); ?></div>
<?php endif; ?>

<div class="epc-entry-image">
  <?php if ( epc_is_lightbox_enabled() ) : ?>

    <a href="<?php echo epc_get_entry_image_url( $entry, 'large' ); ?>" title="<?php echo esc_attr( $entry->name ); ?>" class="epc-lightbox">
      <?php echo epc_get_entry_image( $entry, 'large' ); ?>
      <div class="epc-zoom-icon"><i class="fas fa-search"></i></div>
    </a>

  <?php else: ?>

    <?php echo epc_get_entry_image( $entry, 'large' ); ?>

  <?php endif; ?>
</div>

<?php do_action( 'epc_before_entry_description' ); ?>

<div class="epc-entry-description"><?php echo apply_filters( 'epc_entry_description', wpautop( $entry->description ) ); ?></div>

<?php do_action( 'epc_after_entry_description' ); ?>

<div class="epc-entry-meta">
  <div class="epc-entry-date"><?php printf( __( "Posted: %s ago", EPC_TEXT_DOMAIN ), human_time_diff( current_time('U', strtotime( $entry->date ) ) ) ); ?></div>
  <div class="epc-contestant-name"><span><?php _e('Contestant:', EPC_TEXT_DOMAIN ); ?> <?php echo epc_get_entry_contestant_name( $entry ); ?></span></div>
  <div class="epc-entry-votes"><span><?php _e( 'Votes:', EPC_TEXT_DOMAIN ); ?></span> <?php echo dtp_get_total_votes_entry( $entry->entry_id ); ?></div>
</div>

<?php if ( epc_is_contest_active( $post->ID ) ) : ?>

  <?php echo do_shortcode( '[epc_vote_form]' ); ?>

<?php else: ?>

  <div class="epc-msg"><?php _e( 'This contest is not active at this moment.', EPC_TEXT_DOMAIN ); ?></div>

<?php endif; ?>

<?php echo epc_get_entry_social_sharing( $entry ); ?>

<a href="<?php echo get_permalink( $entry->contest_id ); ?>"><?php _e( 'Back to contest page', EPC_TEXT_DOMAIN ); ?></a>
