jQuery(document).ready(function($) {
    window.printMarksheet = function(studentId, instituteName, logoUrl) {
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
            institute_name: instituteName,
            logo_url: logoUrl,
            nonce: examResultAjax.print_nonce
        };

        $.post(examResultAjax.ajaxurl, data, function(response) {
            // Replace content with actual marksheet
            printWindow.document.open();
            printWindow.document.write(response);
            printWindow.document.close();
            printWindow.print();
        }).fail(function() {
            printWindow.document.open();
            printWindow.document.write('<html><head><title>Error</title></head><body><p>Failed to load marksheet. Please try again.</p></body></html>');
            printWindow.document.close();
        });
    };
});