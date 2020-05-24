(function( $ ) {
  $(document).ready(function(){

    /**
     * Delete warning
     */
    $('.epc-delete').click(function(){
      if ( ! confirm( epc_admin.confirm_text ) ) {
        return false;
      }
    });

    /**
     * Select all
     */
    $('.epc-check-all-entries').change(function() {
      var epc_entry_checkboxes = $('.epc-entry-checkbox:checkbox');
      epc_entry_checkboxes.prop('checked', $(this).is(':checked'));
    });

    /**
     * Tabs
     */
    $('.epc-section').not('.epc-active-section').hide();
    $('#epc-settings-nav .nav-tab').not('a.nav-tab-active');

    $('.epc-nav-tab .nav-tab').click(function(e){
      e.preventDefault();

      $('.epc-nav-tab .nav-tab').removeClass('nav-tab-active');

      $(this).toggleClass('nav-tab-active');

      var epc_section = $(this).data('dtpPcSection');

      $('.epc-section').hide();

      $('#epc-'+epc_section+'-section').toggle();

    });

    /**
     * Chosen
     */
    $(".epc-chosen-select").chosen({width: "50%"});

    /**
     * Confirmation url
     */
    $('.epc-confirmation-url-toggle').click(function(e){
      e.preventDefault();
      var epc_vote_id = $(this).data('dtpPcVoteId');
      console.log(epc_vote_id);
      $('.epc-confirmation-url-'+epc_vote_id).toggle();
    });
  });

}) ( jQuery );
