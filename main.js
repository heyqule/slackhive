/**
 * Created by heyqule on 10/01/16.
 */
$(document).ready(function() {
    $('.is-pin').addClass('clickable');

    $('.is-pin.clickable').click(function(){
        var jThis = $(this);
        var jThisParent = jThis.parent();
        jThisParent.find('.before-pin, .after-pin, .msg-close').show();
        jThisParent.find('.msg-close').addClass('clickable');
        jThis.removeClass('clickable');


        jThisParent.find('.msg-close').on('click',function() {
            var jThisClose = $(this);
            jThisClose.removeClass('clickable');
            $(jThisClose.parent()).find('.before-pin, .after-pin, .msg-close').hide();
            $(document).scrollTop(jThisClose.parent().position().top);
            jThisClose.parent().addClass('clickable');
        });

    });


});