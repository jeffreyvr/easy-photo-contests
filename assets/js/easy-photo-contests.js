(function($) {

  $(document).ready(function() {

    $('.epc-load-more-entries').on('click', function(){

      var epc_load_more_btn    = $(this);
      var epc_contest_id       = epc_load_more_btn.data('contestId');
      var epc_entry_container  = $('.epc-entry-wrapper');
      var epc_entry_offset     = epc_entry_container.find('.epc-entry-item').length;
      var epc_orderby          = epc_load_more_btn.data('orderby');
      var epc_order            = epc_load_more_btn.data('order');

      jQuery.ajax({
        url : dtp_pc.ajax_url,
        type : 'post',
        dataType: 'json',
        data : {
          action : 'epc_load_more_entries',
          contest_id : epc_contest_id,
          offset : epc_entry_offset,
          order : epc_order,
          orderby : epc_orderby
        },
        success : function( epc_entries ) {

          if ( epc_entries.length < 1 ) {

            epc_load_more_btn.hide();

          } else {

            $.each( epc_entries, function( epc_entry_i, epc_entry_item ) {
              var dtp_item_object= $(epc_entry_item);

              dtp_item_object.hide();
              epc_entry_container.append( dtp_item_object );
              dtp_item_object.delay( 100 * epc_entry_i ).fadeIn();
            });

          }

        }
      });

    });

    $('.epc-copy-clipboard').click(function(e){
      e.preventDefault();

      var $temp = $("<input>");
      alert(dtp_pc.copy_clipboard);
      $("body").append($temp);
      $temp.val($(this).attr('href')).select();
      document.execCommand("copy");
      $temp.remove();
    });

    $('.epc-form').on('submit', function(e){ // Prevent double submits
      $(this).find('.epc-button').prop('disabled',true);
    });

  });

})( jQuery );
