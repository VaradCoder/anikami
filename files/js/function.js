$(document).ready(function () {

    /* =========================
       TOOLTIP (FIXED)
       ========================= */
    $('a.tooltipEl, img.tooltipEl').tooltip({
        classes: {
            "ui-tooltip": "highlight"
        },
        position: {
            my: 'left center',
            at: 'right center'
        },
        content: function (callback) {
            const animeId = $(this).attr('animeid');

            if (!animeId) return "No data";

            $.post('/ajax/tooltip', { animeid: animeId }, function (data) {
                callback(data);
            }).fail(() => {
                callback("Error loading");
            });
        }
    });

    /* =========================
       SEARCH (OPTIMIZED)
       ========================= */
    let timeout = null;

    $("#searching").keyup(function () {
        const searchText = $(this).val().trim();

        clearTimeout(timeout);

        timeout = setTimeout(() => {

            if (searchText.length > 0) {
                $.ajax({
                    url: "/theme/6anime/pages/ajax.search.php",
                    method: "POST",
                    data: { query: searchText },
                    success: function (response) {
                        $("#search-suggest").html(response).show();
                    },
                    error: function () {
                        $("#search-suggest").html("<div>Error loading</div>");
                    }
                });
            } else {
                $("#search-suggest").html("").hide();
            }

        }, 300); // debounce delay

    });

    /* =========================
       CLICK SELECT FIX
       ========================= */
    $(document).on("click", "#search-suggest a", function () {
        $("#searching").val($(this).text());
        $("#search-suggest").html("").hide();
    });

});