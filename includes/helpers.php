<?php

defined('ABSPATH') || exit;

/**
 * Check whether the Release Manager plugin is active.
 *
 * @return bool
 */
function mds_woo_is_active()
{
    $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));

    return in_array('mds-release-manager/mds-release-manager.php', $active_plugins, true)
        || (
            is_multisite()
            && array_key_exists(
                'mds-release-manager/mds-release-manager.php',
                get_site_option('active_sitewide_plugins', array())
            )
        );
}

/**
 * Renders WooCommerce integration templates.
 */
final class MdsWooTemplateRenderer
{
    /**
     * Load a template and safely substitute its placeholders.
     *
     * @param string $template_name Template filename.
     * @param array  $placeholders Placeholder values with optional output contexts.
     * @return string Rendered template.
     */
    public static function render($template_name, $placeholders = array())
    {
        $template_path = self::resolveTemplatePath($template_name);
        $template = self::loadTemplate($template_path);

        return self::replacePlaceholders($template, $placeholders);
    }

    /**
     * Resolve a theme override or the plugin's default template.
     *
     * @param string $template_name Template filename.
     * @return string Template path.
     */
    private static function resolveTemplatePath($template_name)
    {
        if (
            basename($template_name) !== $template_name
            || 'html' !== pathinfo($template_name, PATHINFO_EXTENSION)
        ) {
            wp_die(
                esc_html__(
                    'The requested WooCommerce integration template is invalid.',
                    'mds-woocommerce-integration'
                )
            );
        }

        $theme_template = locate_template('mds-woocommerce-integration/' . $template_name);
        $template_path = $theme_template ? $theme_template : MDS_WOO_PATH . 'templates/' . $template_name;

        if (! file_exists($template_path)) {
            wp_die(
                esc_html__(
                    'The requested WooCommerce integration template could not be found.',
                    'mds-woocommerce-integration'
                )
            );
        }

        return $template_path;
    }

    /**
     * Read a template from disk.
     *
     * @param string $template_path Template path.
     * @return string Template contents.
     */
    private static function loadTemplate($template_path)
    {
        $template = file_get_contents($template_path);

        if (false === $template) {
            wp_die(
                esc_html__(
                    'The requested WooCommerce integration template could not be read.',
                    'mds-woocommerce-integration'
                )
            );
        }

        return $template;
    }

    /**
     * Escape and substitute template placeholders.
     *
     * @param string $template Template contents.
     * @param array  $placeholders Placeholder values.
     * @return string Rendered template.
     */
    private static function replacePlaceholders($template, $placeholders)
    {
        $replacements = array();

        foreach ($placeholders as $name => $placeholder) {
            $replacements['{{' . $name . '}}'] = self::escapePlaceholder($placeholder);
        }

        return strtr($template, $replacements);
    }

    /**
     * Escape a placeholder for its output context.
     *
     * @param mixed $placeholder Placeholder descriptor or value.
     * @return string Escaped placeholder.
     */
    private static function escapePlaceholder($placeholder)
    {
        $context = 'text';
        if (is_array($placeholder) && array_key_exists('value', $placeholder)) {
            $context = isset($placeholder['context']) ? $placeholder['context'] : 'text';
            $value = $placeholder['value'];
        } else {
            $value = $placeholder;
        }

        switch ($context) {
            case 'attr':
                return esc_attr($value);
            case 'url':
                return esc_url($value);
            case 'html':
                return wp_kses($value, self::getAllowedHtml());
            default:
                return esc_html($value);
        }
    }

    /**
     * Get the allowed markup for generated HTML fragments.
     *
     * @return array Allowed HTML tags and attributes.
     */
    private static function getAllowedHtml()
    {
        return array(
            'br'     => array(),
            'button' => array(
                'class'         => true,
                'data-post-id'  => true,
                'data-store-id' => true,
                'style'         => true,
                'type'          => true,
            ),
            'div'    => array(
                'class' => true,
                'style' => true,
            ),
            'input'  => array(
                'checked'       => true,
                'data-post-id'  => true,
                'data-store-id' => true,
                'name'          => true,
                'style'         => true,
                'type'          => true,
                'value'         => true,
            ),
            'label'  => array(
                'style' => true,
            ),
            'option' => array(
                'data-permalink' => true,
                'selected'       => true,
                'value'          => true,
            ),
            'p'      => array(
                'class' => true,
                'style' => true,
            ),
            'select' => array(
                'class'         => true,
                'data-store-id' => true,
                'name'          => true,
                'style'         => true,
            ),
            'strong' => array(),
            'table'  => array(
                'class' => true,
                'style' => true,
            ),
            'td'     => array(),
            'th'     => array(
                'scope' => true,
            ),
            'tr'     => array(),
        );
    }
}

/**
 * Render a WooCommerce integration template.
 *
 * @param string $template_name Template filename.
 * @param array  $placeholders Placeholder values.
 * @return string Rendered template.
 */
function mds_woo_render_html_template($template_name, $placeholders = array())
{
    return MdsWooTemplateRenderer::render($template_name, $placeholders);
}
