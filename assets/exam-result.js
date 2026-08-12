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
            // Load the marksheet via a real navigation (a Blob URL) instead of
            // document.write(). Two reasons:
            // 1) document.write()'d content can report readyState "complete"
            //    before its own <img> tags (the institute logo) have actually
            //    finished loading, so print() could still fire too early.
            //    A genuine navigation gives a "load" event that correctly
            //    waits for images.
            // 2) The window never leaves "about:blank" with document.write(),
            //    which is what shows up as "about:blank" in the browser's own
            //    printed header/footer. A real (blob:) URL replaces that.
            var blob = new Blob( [ response ], { type: 'text/html' } );
            var blobUrl = URL.createObjectURL( blob );

            printWindow.onload = function() {
                printWindow.focus();
                printWindow.print();
                URL.revokeObjectURL( blobUrl );
            };
            printWindow.location.href = blobUrl;
        }).fail(function() {
            printWindow.document.open();
            printWindow.document.write('<html><head><title>Error</title></head><body><p>Failed to load marksheet. Please try again.</p></body></html>');
            printWindow.document.close();
        });
    };
});
