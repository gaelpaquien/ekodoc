<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            margin: 0;
            padding: 2rem;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            color: #171717;
        }

        /* Mirrors the .tiptap-content rules in Editor.vue/Show.vue so the
           exported PDF renders headings/lists/tables/images the same way
           they looked while drafting (Code Map, spec-2-4). No dark-mode
           variant here — a PDF has no theme to react to. */
        .tiptap-content h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0.75rem 0 0.5rem;
        }

        .tiptap-content h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0.75rem 0 0.5rem;
        }

        .tiptap-content h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0.75rem 0 0.5rem;
        }

        .tiptap-content p {
            margin: 0.5rem 0;
        }

        .tiptap-content img {
            max-width: 100%;
            height: auto;
            margin: 0.75rem 0;
            border-radius: 0.25rem;
        }

        .tiptap-content ul {
            list-style: disc;
            padding-left: 1.5rem;
            margin: 0.5rem 0;
        }

        .tiptap-content ol {
            list-style: decimal;
            padding-left: 1.5rem;
            margin: 0.5rem 0;
        }

        .tiptap-content table {
            border-collapse: collapse;
            width: 100%;
            margin: 0.75rem 0;
        }

        .tiptap-content table td,
        .tiptap-content table th {
            border: 1px solid #d4d4d4;
            padding: 0.375rem 0.5rem;
        }

        .tiptap-content table th {
            background-color: #f5f5f5;
            font-weight: 600;
            text-align: left;
        }
    </style>
</head>
<body>
    {{-- content_html was already sanitized down to the allowed-tags list
         (SanitizesDocumentContent) before being persisted, and its <img
         src> attributes are rewritten to data URIs by
         ExportDocumentToPdfAction before this view is ever rendered — see
         Design Notes, spec-2-4. --}}
    <div class="tiptap-content">
        {!! $contentHtml !!}
    </div>
</body>
</html>
