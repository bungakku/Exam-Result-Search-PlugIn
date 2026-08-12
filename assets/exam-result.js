jQuery(document).ready(function($) {
    window.printMarksheet = function(studentId) {
        // Open print window immediately (user gesture) – this avoids popup blockers
        var printWindow = window.open('', '_blank');
        if (!printWindow) {
            alert('Please allow popups for this website to print marksheets.');
            return;
        }

        // Show a loading message
        printWindow.document.write('<html><head><title>Loading...</title></head><body>Loading marksheet...</body></html>');
        printWindow.document.close();

        // Fetch marksheet content via AJAX
        var data = {
            action: 'print_marksheet',
            post_id: studentId,
            nonce: examResultAjax.print_nonce
        };

        $.post(examResultAjax.ajaxurl, data, function(response) {
            // Replace content with actual marksheet
            printWindow.document.open();
            printWindow.document.write(response);
            printWindow.document.close();

            // Wait for the marksheet document -- including the institute
            // logo <img> -- to fully load before opening the print dialog,
            // so the logo isn't missing/blank in the printout.
            function triggerPrint() {
                printWindow.focus();
                printWindow.print();
            }
            if (printWindow.document.readyState === 'complete') {
                triggerPrint();
            } else {
                printWindow.onload = triggerPrint;
            }
        }).fail(function() {
            printWindow.document.open();
            printWindow.document.write('<html><head><title>Error</title></head><body><p>Failed to load marksheet. Please try again.</p></body></html>');
            printWindow.document.close();
        });
    };
});
