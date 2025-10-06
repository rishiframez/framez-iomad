# Framez Webservice Plugin

A Moodle webservice plugin that creates course pages with summary and cue cards content.

## Features

- **Webservice API**: Create course pages programmatically via webservice calls
- **Summary Content**: Support for rich summary data including title, description, author, subject, level, and tags
- **Interactive Cue Cards**: Create flip-able cue cards with front/back content, categories, difficulty levels, and tags
- **Responsive Design**: Mobile-friendly layout with CSS animations
- **Security**: Proper capability checks and input validation
- **Templates**: Clean Mustache templates for content rendering

## Installation

1. Copy the plugin files to `/local/framez_webservice/` in your Moodle installation
2. Visit the Moodle admin page to install the plugin
3. Configure webservice access in Site administration > Server > Web services

## Usage

### Webservice Function

**Function Name**: `local_framez_webservice_create_course_page`

**Parameters**:
- `courseid` (int): Course ID where the page will be created
- `summary` (string): JSON string containing summary data
- `cuecards` (string): JSON string containing cue cards array

### Summary JSON Structure

```json
{
  "title": "Page Title",
  "description": "Page description content",
  "author": "Author Name",
  "subject": "Subject Area",
  "level": "Beginner|Intermediate|Advanced",
  "tags": ["tag1", "tag2", "tag3"],
  "created_date": "2024-01-01T00:00:00Z",
  "last_modified": "2024-01-01T00:00:00Z"
}
```

### Cue Cards JSON Structure

```json
[
  {
    "id": "unique_card_id",
    "front": "Front side content",
    "back": "Back side content",
    "category": "Category Name",
    "difficulty": "easy|medium|hard",
    "tags": ["tag1", "tag2"]
  }
]
```

### Example Usage

```php
// Example webservice call
$result = $client->call_function('local_framez_webservice_create_course_page', [
    'courseid' => 123,
    'summary' => json_encode([
        'title' => 'French Vocabulary',
        'description' => 'Learn basic French vocabulary',
        'author' => 'Language Teacher',
        'subject' => 'French',
        'level' => 'Beginner',
        'tags' => ['french', 'vocabulary', 'language']
    ]),
    'cuecards' => json_encode([
        [
            'id' => 'card1',
            'front' => 'Bonjour',
            'back' => 'Hello',
            'category' => 'Greetings',
            'difficulty' => 'easy'
        ],
        [
            'id' => 'card2',
            'front' => 'Comment allez-vous?',
            'back' => 'How are you?',
            'category' => 'Greetings',
            'difficulty' => 'medium'
        ]
    ])
]);
```

## Capabilities

- `local/framez_webservice:create_course_page`: Create course pages with summary and cue cards

## Requirements

- Moodle 4.0+
- Page module (core Moodle module)
- Mustache templating engine (core Moodle)

## File Structure

```
local/framez_webservice/
├── version.php                    # Plugin version information
├── db/
│   ├── access.php                # Capability definitions
│   └── services.php              # Webservice definitions
├── classes/
│   ├── external.php              # Main external API class
│   └── page_renderer.php         # Content renderer class
├── lang/en/
│   └── local_framez_webservice.php # Language strings
├── templates/
│   └── page_content.mustache     # Page content template
├── tests/
│   └── external_test.php         # Unit tests
├── lib.php                       # Helper functions
├── externallib.php               # Legacy external API
└── README.md                     # This file
```

## Development

### Running Tests

```bash
vendor/bin/phpunit local/framez_webservice/tests/external_test.php
```

### Adding New Features

1. Update the external API class in `classes/external.php`
2. Add corresponding tests in `tests/external_test.php`
3. Update language strings in `lang/en/local_framez_webservice.php`
4. Update this README if needed

## License

This plugin is licensed under the GNU GPL v3 or later.

## Support

For support and bug reports, please contact the Framez development team.




