# drupal_document

Provides a Drupal Document content type to store organisational multilingual 
structured PDF/Word etc. documents

# Installation

In `composer.json`:

1. In `"repositories":[]` add:
```
{
    "type": "git",
    "url": "git@github.com:eaudeweb/drupal_document.git"
}
```

2. A SSH Key is required.

3. Run: ```composer require drupal/drupal_document```

## Basic Configuration

- Field type
  - **File with Language** - File with description and language
- Field widget
  - File with language - default widget for the `File with Language` field type.
  - Multi Language file - display all files grouped by language.
- Field Formatter:
  - File with Language - File formatter for `file_language` field type. Extends 
generic File formatter with the 
possibility to display the language selected from a dropdown with languages. If 
`use_description_as_link_text` setting is true, then show description if 
language is not selected. If both are empty then display the filename. Use
`suppress_language` to suppress the language with description.
  - Dropdown File with Language - overrides the default File with Language 
formatter and display only files with language as a dropdown (using 
`dropdown_file_language` theme).
  - Files group by Language - Group files in tabs using available languages.
- Facet Processor **List item Language** - Display the language name instead 
of langcode.
