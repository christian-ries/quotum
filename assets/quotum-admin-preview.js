(function () {
        const quoteInput = document.getElementById("quote");
        const authorInput = document.getElementById("author");
        const sourceInput = document.getElementById("source");
        const previewQuote = document.getElementById("quotum-preview-quote");
        const previewAuthor = document.getElementById("quotum-preview-author");
        const previewSource = document.getElementById("quotum-preview-source");

        if (!quoteInput || !authorInput || !sourceInput || !previewQuote || !previewAuthor || !previewSource) {
            return;
        }

        function updatePreview() {
            previewQuote.textContent = quoteInput.value;
            previewAuthor.textContent = authorInput.value.trim() ? "— " + authorInput.value.trim() : "";
            previewSource.textContent = sourceInput.value.trim() ? sourceInput.value.trim() : "";

            previewAuthor.style.display = authorInput.value.trim() ? "block" : "none";
            previewSource.style.display = sourceInput.value.trim() ? "block" : "none";
        }

        [quoteInput, authorInput, sourceInput].forEach(function (field) {
            field.addEventListener("input", updatePreview);
        });

        updatePreview();
    })();
