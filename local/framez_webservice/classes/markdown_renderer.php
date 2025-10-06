<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_framez_webservice;

/**
 * Markdown renderer for Framez Webservice
 *
 * @package    local_framez_webservice
 * @copyright  2024 Framez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class markdown_renderer {

    /**
     * Render markdown text to HTML
     *
     * @param string $markdown_text Raw markdown text
     * @return string Rendered HTML
     */
    public static function render_markdown($markdown_text) {
        if (empty($markdown_text)) {
            return '';
        }

        $html = $markdown_text;
        
        // Process markdown elements in order
        $html = self::parse_markdown_code_blocks($html);
        $html = self::parse_markdown_headers($html);
        $html = self::parse_markdown_horizontal_rules($html);
        $html = self::parse_markdown_blockquotes($html);
        $html = self::parse_markdown_lists($html);
        $html = self::parse_markdown_links($html);
        $html = self::parse_markdown_emphasis($html);
        $html = self::parse_markdown_inline_code($html);
        $html = self::parse_markdown_line_breaks($html);
        
        // Sanitize the final HTML
        $html = self::sanitize_html($html);
        
        return $html;
    }

    /**
     * Extract title from markdown text (first header)
     *
     * @param string $markdown_text Raw markdown text
     * @return string Extracted title or empty string
     */
    public static function extract_title_from_markdown($markdown_text) {
        $lines = explode("\n", $markdown_text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^#+\s+(.+)$/', $line, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return '';
    }

    /**
     * Parse markdown headers
     *
     * @param string $text Text to parse
     * @return string Parsed text
     */
    private static function parse_markdown_headers($text) {
        // Process headers from largest to smallest
        for ($i = 6; $i >= 1; $i--) {
            $pattern = '/^' . str_repeat('#', $i) . '\s+(.+)$/m';
            $replacement = '<h' . $i . ' class="markdown-header markdown-h' . $i . '">$1</h' . $i . '>';
            $text = preg_replace($pattern, $replacement, $text);
        }
        
        return $text;
    }

    /**
     * Parse markdown lists (ordered and unordered)
     *
     * @param string $text Text to parse
     * @return string Parsed text
     */
    private static function parse_markdown_lists($text) {
        $lines = explode("\n", $text);
        $in_list = false;
        $list_type = '';
        $result = array();
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Check for unordered list
            if (preg_match('/^[-*+]\s+(.+)$/', $trimmed, $matches)) {
                if (!$in_list || $list_type !== 'ul') {
                    if ($in_list) {
                        $result[] = '</' . $list_type . '>';
                    }
                    $result[] = '<ul class="markdown-list markdown-ul">';
                    $in_list = true;
                    $list_type = 'ul';
                }
                $result[] = '<li class="markdown-list-item">' . $matches[1] . '</li>';
            }
            // Check for ordered list
            elseif (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $matches)) {
                if (!$in_list || $list_type !== 'ol') {
                    if ($in_list) {
                        $result[] = '</' . $list_type . '>';
                    }
                    $result[] = '<ol class="markdown-list markdown-ol">';
                    $in_list = true;
                    $list_type = 'ol';
                }
                $result[] = '<li class="markdown-list-item">' . $matches[1] . '</li>';
            }
            // Not a list item
            else {
                if ($in_list) {
                    $result[] = '</' . $list_type . '>';
                    $in_list = false;
                    $list_type = '';
                }
                $result[] = $line;
            }
        }
        
        // Close any remaining list
        if ($in_list) {
            $result[] = '</' . $list_type . '>';
        }
        
        return implode("\n", $result);
    }

    /**
     * Parse markdown emphasis (bold and italic)
     *
     * @param string $text Text to parse
     * @return string Parsed text
     */
    private static function parse_markdown_emphasis($text) {
        // Bold text (**text** or __text__)
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong class="markdown-bold">$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong class="markdown-bold">$1</strong>', $text);
        
        // Italic text (*text* or _text_)
        $text = preg_replace('/\*(.+?)\*/', '<em class="markdown-italic">$1</em>', $text);
        $text = preg_replace('/_(.+?)_/', '<em class="markdown-italic">$1</em>', $text);
        
        // Strikethrough (~~text~~)
        $text = preg_replace('/~~(.+?)~~/', '<del class="markdown-strikethrough">$1</del>', $text);
        
        return $text;
    }

    /**
     * Parse markdown links
     *
     * @param string $text Text to parse
     * @return string Parsed text
     */
    private static function parse_markdown_links($text) {
        // Links [text](url)
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" class="markdown-link" target="_blank" rel="noopener">$1</a>', $text);
        
        return $text;
    }

    /**
     * Parse markdown code blocks
     *
     * @param string $text Text to parse
     * @return string Parsed text
     */
    private static function parse_markdown_code_blocks($text) {
        // Code blocks (```code```)
        $text = preg_replace('/```([^`]+)```/s', '<pre class="markdown-code-block"><code>$1</code></pre>', $text);
        
        return $text;
    }

    /**
     * Parse markdown inline code
     *
     * @param string $text Text to parse
     * @return string Parsed text
     */
    private static function parse_markdown_inline_code($text) {
        // Inline code (`code`)
        $text = preg_replace('/`([^`]+)`/', '<code class="markdown-inline-code">$1</code>', $text);
        
        return $text;
    }

    /**
     * Parse markdown blockquotes
     *
     * @param string $text Text to parse
     * @return string Parsed text
     */
    private static function parse_markdown_blockquotes($text) {
        $lines = explode("\n", $text);
        $in_quote = false;
        $result = array();
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if (preg_match('/^>\s*(.+)$/', $trimmed, $matches)) {
                if (!$in_quote) {
                    $result[] = '<blockquote class="markdown-blockquote">';
                    $in_quote = true;
                }
                $result[] = '<p class="markdown-quote-text">' . $matches[1] . '</p>';
            } else {
                if ($in_quote) {
                    $result[] = '</blockquote>';
                    $in_quote = false;
                }
                $result[] = $line;
            }
        }
        
        if ($in_quote) {
            $result[] = '</blockquote>';
        }
        
        return implode("\n", $result);
    }

    /**
     * Parse markdown horizontal rules
     *
     * @param string $text Text to parse
     * @return string Parsed text
     */
    private static function parse_markdown_horizontal_rules($text) {
        // Horizontal rules (--- or ***)
        $text = preg_replace('/^[-*]{3,}$/m', '<hr class="markdown-hr">', $text);
        
        return $text;
    }

    /**
     * Parse markdown line breaks and paragraphs
     *
     * @param string $text Text to parse
     * @return string Parsed text
     */
    private static function parse_markdown_line_breaks($text) {
        // Convert double line breaks to paragraphs
        $text = preg_replace('/\n\s*\n/', '</p><p class="markdown-paragraph">', $text);
        
        // Wrap in paragraph tags
        $text = '<p class="markdown-paragraph">' . $text . '</p>';
        
        // Clean up empty paragraphs
        $text = preg_replace('/<p class="markdown-paragraph">\s*<\/p>/', '', $text);
        
        return $text;
    }

    /**
     * Sanitize HTML output
     *
     * @param string $html HTML to sanitize
     * @return string Sanitized HTML
     */
    private static function sanitize_html($html) {
        // Use Moodle's HTML sanitization
        $html = clean_param($html, PARAM_CLEANHTML);
        
        return $html;
    }
}


