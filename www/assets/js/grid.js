$(document).ready(function() {
    $('th.header-ordered').on('click', tableOrderedHeaderClick);
    $('.submit').on('click', function () {
        $(this).closest('form').submit();
    });

    // Cancel search button clears all filter input
    $('a.search-cancel').on('click', function () {
        const $filterRow = $(this).closest('tr');
        $('input', $filterRow).val('');
        $('select', $filterRow).val('');
        $filterRow.closest('form').submit();
    });

    // Auto-submit on filter input
    $('tr.search input').on('keyup', function (e) {
        e.preventDefault();
        if (e.keyCode === 13) $(this).closest('form').submit();
    });

    // Auto-submit on filter selection change
    $('tr.search select').on('change', function () {
        $(this).closest('form').submit();
    });
});

/**
 * Click on the sortable header cell. Switches order in cycle: null -> ASC -> DESC -> null
 *
 * All columns have a sorting order ('ASC'/'DESC' or ''=none) and a sorting priority (integer).
 * With different priorities, we can sort the table by multiple columns.
 *
 * The default click on a sorted column reverses the sorting order. (And makes the column the first priority if was not)
 * The default click on a non-sorted column deletes previous sorting and makes the column the 1-priority
 *
 * Ctrl-click on a non-sorted column adds a secondary order.
 * Ctrl-click on a sorted column reverses the secondary order.
 */
function tableOrderedHeaderClick(ev) {
    const $row = $(this).closest('tr');
    const $inpOrd = $('input', this);
    // The actual sorting direction of the clicked column ('ASC'/'DESC'/'')
    const actOrd = $inpOrd.val() !== '' ? $inpOrd.val().split(';')[0] : '';
    // The actual sorting priority of the clicked column (1,...)
    const actPrio = $inpOrd.val() !== '' ? $inpOrd.val().split(';')[1] : '';

    if(ev.ctrlKey) {
        const ord = actOrd === '' ? 'ASC' : (actOrd === 'ASC' ? 'DESC' : '');
        let prio = actPrio;
        if (ord) {
            // Finds the max priority number, except itself
            let max = 0;
            $('input', $row).each(function () {
                if ($(this).val().split(';')[1] === prio) return;
                const a = $(this).val() !== '' ? parseInt($(this).val().split(';')[1]) : 0;
                if (a > max) max = a;
            });
            prio = max + 1;
        } else {
            // Decreases all higher priority number
            prio = '';
            $('input', $row).each(function () {
                const vv = $(this).val() ? $(this).val().split(';') : ['', ''];
                if (vv[0] !== '' && vv[1] !== '' && parseInt(vv[1]) > parseInt(actPrio)) $(this).val('' + vv[0] + ';' + (parseInt(vv[1]) - 1));
            });
        }
        $inpOrd.val(ord ? ord + ';' + parseInt(prio) : '');
        $inpOrd.attr('disabled', !ord);
    }
    else {
        // Delete all orders
        $('input', $row).each(function () {
            $(this).val('');
            $(this).attr('disabled', true);
        });
        // Reverse the current order
        const ord = actOrd === 'ASC' ? 'DESC' : 'ASC';
        const prio = 1;
        $inpOrd.val(ord + ';' + prio);
        $inpOrd.attr('disabled', false);
        console.log(ord, prio);
    }
    $(this).closest('form').submit();
}
