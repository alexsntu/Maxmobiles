{* Maxmobiles (копия хука GOODMi из addons/gm_delivery_date/hooks): список в админке – отметка «подтверждён ли номер телефона» (см. manage_data.post.tpl). Отметку выдаёт отдельная
   колонка, а скрипт ниже переносит её в ячейку с телефоном, сразу за номером, и убирает лишнюю колонку. Без скрипта
   колонка «Номер» остаётся видимой – отметка не теряется. *}
<th width="8%" class="mm-mark-head">Номер (Maxmobiles)</th>
<script>
{literal}
(function () {
    function move() {
        var cells = document.querySelectorAll('td.mm-mark-cell'), heads = document.querySelectorAll('th.mm-mark-head'), i;
        for (i = 0; i < cells.length; i++) {
            var cell = cells[i], mark = cell.querySelector('.label'), link = cell.parentNode.querySelector('a[href^="tel:"]');
            if (mark && link) {
                var holder = link.parentNode.tagName === 'BDI' ? link.parentNode.parentNode : link.parentNode;
                holder.appendChild(document.createTextNode(' '));
                holder.appendChild(mark);
            }
            if (!mark || link) {
                cell.style.display = 'none';
            }
        }
        if (!document.querySelector('td.mm-mark-cell .label')) {
            for (i = 0; i < heads.length; i++) {
                heads[i].style.display = 'none';
            }
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', move);
    } else {
        setTimeout(move, 0);
    }
})();
{/literal}
</script>
