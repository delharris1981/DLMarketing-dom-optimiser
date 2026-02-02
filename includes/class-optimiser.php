<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DLMarketing_DOM_Optimiser_Core
 * Handles the actual DOM manipulation.
 */
class DLMarketing_DOM_Optimiser_Core
{

    /**
     * Protected IDs gathered from inline scripts
     *
     * @var array
     */
    private $protected_ids = [];

    /**
     * Instance
     */
    private static $instance = null;

    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        // Init logic if needed
    }

    /**
     * Process Buffer
     * @param string $buffer
     * @return string
     */
    public function process_buffer($buffer)
    {
        if (empty($buffer) || strlen($buffer) < 100) {
            return $buffer;
        }

        // Check settings
        $enable_wrapper = get_option('dlmarketing_dom_enable_wrapper', 'on') === 'on';
        $enable_ghost = get_option('dlmarketing_dom_enable_ghost', 'on') === 'on';
        $enable_comments = get_option('dlmarketing_dom_enable_comments', 'on') === 'on';
        $enable_aggressive = get_option('dlmarketing_dom_enable_aggressive', '') === 'on';

        // If everything disabled, return early
        if (!$enable_wrapper && !$enable_ghost && !$enable_comments && !$enable_aggressive) {
            return $buffer;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        // Force UTF-8 and handle HTML5
        $dom->loadHTML(mb_convert_encoding($buffer, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        // 0. Safety: Scan Scripts for Used IDs
        $this->scan_scripts_for_ids($xpath);

        // 1. Core Pruning: Wrappers
        if ($enable_wrapper) {
            $this->prune_wrappers($xpath);
        }

        // 2. Ghost Node Removal
        if ($enable_ghost) {
            $this->remove_ghost_nodes($xpath);
        }

        // 3. Cleanup: Comments
        if ($enable_comments) {
            $this->remove_comments($xpath);
        }

        // 4. Deep Space: Aggressive
        if ($enable_aggressive) {
            $this->remove_elementor_inner($xpath);
            $this->flatten_redundant_containers($xpath);
        }

        $output = $dom->saveHTML();
        libxml_clear_errors();

        return $output;
    }

    /**
     * Scan inline scripts to find IDs referenced by JS.
     * Adds them to $this->protected_ids.
     * 
     * @param DOMXPath $xpath
     */
    private function scan_scripts_for_ids($xpath)
    {
        $scripts = $xpath->query('//script');
        if (!$scripts)
            return;

        foreach ($scripts as $script) {
            $content = $script->textContent;
            if (empty($content))
                continue;

            // Match getElementById('xyz') or getElementById("xyz")
            if (preg_match_all('/getElementById\s*\(\s*["\']([^"\']+)["\']\s*\)/', $content, $matches)) {
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $id) {
                        $this->protected_ids[$id] = true;
                    }
                }
            }

            // Match jQuery $('#xyz') or $("#xyz")
            if (preg_match_all('/\$\s*\(\s*["\']#([^"\']+)["\']\s*\)/', $content, $matches)) {
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $id) {
                        $this->protected_ids[$id] = true;
                    }
                }
            }
        }
    }

    /**
     * Prune Wrappers
     */
    private function prune_wrappers($xpath)
    {
        $query = "//*[contains(@class, 'elementor-column-wrap') or contains(@class, 'elementor-widget-wrap') or contains(@class, 'elementor-widget-container')]";
        $nodes = $xpath->query($query);

        if (!$nodes)
            return;

        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $node = $nodes->item($i);
            if ($this->is_safe_to_prune($node)) {
                $this->unwrap_node($node);
            }
        }
    }

    /**
     * Is Safe To Prune
     */
    private function is_safe_to_prune($node)
    {
        if (!$node instanceof DOMElement)
            return false;

        // 1. ID Check (Basic existence + JS Scanner check)
        if ($node->hasAttribute('id')) {
            $id = $node->getAttribute('id');
            // If we scanned it in JS, definitely keep it.
            // Even if not in JS, we generally keep IDs in Phase 1/2 for safety.
            return false;
        }

        // 2. Attributes
        $protected_attrs = ['data-id', 'aria-label', 'role', 'tabindex', 'onclick', 'style'];
        foreach ($protected_attrs as $attr) {
            if ($node->hasAttribute($attr))
                return false;
        }

        // 3. User Class Protection
        $classes = explode(' ', $node->getAttribute('class'));
        foreach ($classes as $class) {
            $class = trim($class);
            if (empty($class))
                continue;
            if (strpos($class, 'elementor-') !== 0)
                return false;
        }

        return true;
    }

    /**
     * Unwrap Node
     */
    private function unwrap_node($node)
    {
        $parent = $node->parentNode;
        if (!$parent)
            return;
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }

    /**
     * Remove Ghost Nodes
     */
    private function remove_ghost_nodes($xpath)
    {
        $query = "//*[contains(@class, 'elementor-element')]";
        $nodes = $xpath->query($query);

        if (!$nodes)
            return;

        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $node = $nodes->item($i);

            if ($node->hasAttribute('id'))
                continue;

            $has_media = $xpath->query(".//img | .//svg | .//input | .//iframe | .//video", $node)->length > 0;
            if ($has_media)
                continue;

            if (!empty(trim($node->textContent)))
                continue;

            if ($node->hasAttribute('style'))
                continue;

            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Remove Comments
     */
    private function remove_comments($xpath)
    {
        $comments = $xpath->query('//comment()');
        if (!$comments)
            return;
        for ($i = $comments->length - 1; $i >= 0; $i--) {
            $comments->item($i)->parentNode->removeChild($comments->item($i));
        }
    }

    /**
     * [Aggressive] Remove .elementor-inner
     */
    private function remove_elementor_inner($xpath)
    {
        $query = "//*[contains(@class, 'elementor-inner')]";
        $nodes = $xpath->query($query);
        if (!$nodes)
            return;

        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $node = $nodes->item($i);
            // Safe to prune?
            // For aggressive, we relax slightly, but still check IDs/Events
            if ($this->is_safe_to_prune($node)) {
                $this->unwrap_node($node);
            }
        }
    }

    /**
     * [Aggressive] Flatten Redundant Containers
     * Parent has 1 child, child is container.
     */
    private function flatten_redundant_containers($xpath)
    {
        // Find containers
        $query = "//*[contains(@class, 'elementor-container') or contains(@class, 'elementor-row')]";
        $nodes = $xpath->query($query);

        if (!$nodes)
            return;

        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $node = $nodes->item($i);
            $parent = $node->parentNode;

            if (!$parent || !$parent instanceof DOMElement)
                continue;

            // Check if parent is also a 'wrapper' or 'section' or generic div
            // And if parent has ONLY this child (ignoring text/whitespace)

            // 1. Parent Checks
            if ($parent->hasAttribute('id'))
                continue; // Safety
            // Parent must look like a structural div
            // (Strict check: must be elementor-section or similar wrapper? Or generic div?)
            // Let's stick to Elementor structure: Parent is Section, Child is Container.
            // Often we want to keeping Section > Container. 
            // BUT: Parent .elementor-widget-wrap > Child .elementor-element-populated (Redundant)?

            // Let's target: Parent div with ONE child which is also a div.
            // And Parent has NO styling (Visual Zero).

            // Re-use is_safe_to_prune for the PARENT
            if (!$this->is_safe_to_prune($parent))
                continue;

            // Check children count (ignoring whitespace)
            $child_count = 0;
            foreach ($parent->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $child_count++;
                } elseif ($child instanceof DOMText && trim($child->textContent) !== '') {
                    $child_count++; // Has actual text
                }
            }

            if ($child_count === 1) {
                // Parent is safe, has only 1 child. Unwrap parent!
                // NOTE: We unwrap the PARENT here, effectively lifting the child up.
                $this->unwrap_node($parent);
            }
        }
    }
}
