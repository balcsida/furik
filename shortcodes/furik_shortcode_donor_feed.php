<?php
/**
 * WordPress shortcode: [furik_donor_feed]
 *
 * Displays recent donations for a campaign
 *
 * Basic usage (backward compatible):
 * [furik_donor_feed id="123" count="5"]
 *
 * Extended parameters:
 * - id: Campaign ID (optional, defaults to current post)
 * - count: Number of donations to show (default: 10)
 * - show_time: Show relative time (default: false for backward compatibility)
 * - show_anon: Show anonymous donations (default: true)
 * - min_amount: Minimum amount to display (default: 0)
 * - cache_time: Cache duration in seconds (default: 600)
 * - layout: Display layout - 'simple' (default), 'detailed', or custom template
 * - title: Custom title (optional, default shows Hungarian text for compatibility)
 * - no_donations_text: Text when no donations (optional)
 * - debug: Enable debug output (default: false)
 */
function furik_shortcode_donor_feed($atts) {
    global $wpdb, $furik_name_order_eastern;

    // Parameter defaults - keeping backward compatibility
    $a = shortcode_atts(array(
        'id' => null,
        'count' => 10,
        'show_time' => false,  // Default false for backward compatibility
        'show_anon' => true,
        'min_amount' => 0,
        'cache_time' => 600,
        'layout' => 'simple',  // Default to simple layout for backward compatibility
        'title' => 'Legfrissebb adományozóink:',  // Hungarian default for compatibility
        'no_donations_text' => 'Erre a gyűjtésre még nem érkezett adomány.',  // Hungarian default
        'debug' => false
    ), $atts);

    // Debug mode
    $debug = filter_var($a['debug'], FILTER_VALIDATE_BOOLEAN);

    // Get campaign ID
    $post_id = $a['id'] ? intval($a['id']) : 0;
    if (!$post_id) {
        $current_post = get_post();
        if ($current_post) {
            $post_id = $current_post->ID;
        }
    }

    if (!$post_id) {
        return '<p class="furik-error">Hiba: Nincs kampány ID megadva, és az aktuális post nem elérhető.</p>';
    }

    // Validate parameters
    $count = max(1, min(100, intval($a['count'])));
    $min_amount = max(0, intval($a['min_amount']));
    $cache_time = max(0, intval($a['cache_time']));
    $show_anon = filter_var($a['show_anon'], FILTER_VALIDATE_BOOLEAN);
    $show_time = filter_var($a['show_time'], FILTER_VALIDATE_BOOLEAN);

    if ($debug) {
        echo "<!-- Furik Donor Feed Debug: Campaign ID: $post_id, Count: $count -->\n";
    }

    // Cache key - using the same format as original for compatibility
    $cache_key = "furik_donor_feed_{$post_id}_{$count}";

    // For extended features, use a different cache key
    if ($a['layout'] !== 'simple' || $show_time || $min_amount > 0 || !$show_anon) {
        $cache_key = 'furik_donor_feed_' . md5(serialize($a) . $post_id);
    }

    // Check cache
    if ($cache_time > 0 && !$debug) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            if ($debug) {
                echo "<!-- Furik Donor Feed: Loaded from cache -->\n";
            }
            return $cached;
        }
    }

    // Build query
    $where_conditions = array(
        'campaign = %d',
        'transaction_status IN (' . FURIK_STATUS_DISPLAYABLE . ')'
    );

    $query_params = array($post_id);

    if (!$show_anon) {
        $where_conditions[] = 'anon = 0';
    }

    if ($min_amount > 0) {
        $where_conditions[] = 'amount >= %d';
        $query_params[] = $min_amount;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Query donations - keeping original fields for compatibility
    $sql = "SELECT
                first_name,
                last_name,
                name,
                amount,
                anon,
                time,
                message
             FROM
                {$wpdb->prefix}furik_transactions
             WHERE
                $where_clause
             ORDER BY
                time DESC
             LIMIT %d";

    $query_params[] = $count;

    $results = $wpdb->get_results(
        $wpdb->prepare($sql, $query_params)
    );

    // No results - using original HTML structure
    if (empty($results)) {
        if ($debug) {
            echo "<!-- Furik Donor Feed: No donations found -->\n";
        }
        return '<p class="furik-no-donations">' . esc_html($a['no_donations_text']) . '</p>';
    }

    // Generate HTML - maintaining original structure for backward compatibility
    $output = '<div class="furik-donor-feed">';

    // Add title
    if (!empty($a['title'])) {
        $output .= '<h3>' . esc_html($a['title']) . '</h3>';
    }

    // Process donations based on layout
    if ($a['layout'] === 'simple') {
        // Original simple layout for backward compatibility
        foreach ($results as $donation) {
            $donor_name = $donation->anon ? 'Anonim' : esc_html($donation->last_name . ' ' . $donation->first_name);
            $amount = number_format($donation->amount, 0, ',', ' ');
            $output .= "<div class=\"donor-feed-item\">{$donor_name}: {$amount} Ft</div>";
        }
    } else if ($a['layout'] === 'detailed') {
        // Enhanced detailed layout
        $output .= '<div class="furik-donor-feed-list">';
        foreach ($results as $donation) {
            $output .= furik_render_donor_item($donation, $show_time, $furik_name_order_eastern);
        }
        $output .= '</div>';
    } else {
        // Custom layout template
        foreach ($results as $donation) {
            $output .= furik_render_custom_donor_item($donation, $a['layout'], $show_time, $furik_name_order_eastern);
        }
    }

    $output .= '</div>';

    // Add styles only for non-simple layouts
    if ($a['layout'] !== 'simple' && !wp_style_is('furik-donor-feed-enhanced', 'enqueued')) {
        $output .= furik_donor_feed_enhanced_styles();
        wp_register_style('furik-donor-feed-enhanced', false);
        wp_enqueue_style('furik-donor-feed-enhanced');
    }

    // Cache the output
    if ($cache_time > 0) {
        set_transient($cache_key, $output, $cache_time);
    }

    if ($debug) {
        echo "<!-- Furik Donor Feed: Generated fresh output -->\n";
    }

    return $output;
}

/**
 * Render detailed donor item
 */
function furik_render_donor_item($donation, $show_time = false, $name_order_eastern = false) {
    // Get donor name
    if ($donation->anon) {
        $donor_name = __('Anonymous', 'furik');
    } else {
        if (!empty($donation->name)) {
            $donor_name = esc_html($donation->name);
        } else {
            if ($name_order_eastern) {
                $donor_name = esc_html(trim($donation->last_name . ' ' . $donation->first_name));
            } else {
                $donor_name = esc_html(trim($donation->first_name . ' ' . $donation->last_name));
            }
        }
    }

    $amount = number_format($donation->amount, 0, ',', ' ');

    $html = '<div class="donor-feed-item-detailed">';
    $html .= '<span class="donor-name">' . $donor_name . '</span>';
    $html .= '<span class="donor-amount">' . $amount . ' Ft</span>';

    if ($show_time) {
        $html .= '<span class="donor-time">' . furik_time_ago($donation->time) . '</span>';
    }

    if (!empty($donation->message) && !$donation->anon) {
        $html .= '<span class="donor-message">' . esc_html($donation->message) . '</span>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render custom template donor item
 */
function furik_render_custom_donor_item($donation, $template, $show_time = false, $name_order_eastern = false) {
    // Get donor name
    if ($donation->anon) {
        $donor_name = __('Anonymous', 'furik');
    } else {
        if (!empty($donation->name)) {
            $donor_name = esc_html($donation->name);
        } else {
            if ($name_order_eastern) {
                $donor_name = esc_html(trim($donation->last_name . ' ' . $donation->first_name));
            } else {
                $donor_name = esc_html(trim($donation->first_name . ' ' . $donation->last_name));
            }
        }
    }

    $amount = number_format($donation->amount, 0, ',', ' ');
    $time_html = $show_time ? furik_time_ago($donation->time) : '';
    $message = !empty($donation->message) && !$donation->anon ? esc_html($donation->message) : '';

    // Replace placeholders
    return str_replace(
        array('{donor_name}', '{amount}', '{time}', '{message}'),
        array($donor_name, $amount, $time_html, $message),
        $template
    );
}

/**
 * Convert time to relative format
 */
function furik_time_ago($datetime) {
    $time = strtotime($datetime);
    $now = current_time('timestamp');
    $diff = $now - $time;

    if ($diff < 60) {
        return __('just now', 'furik');
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return sprintf(_n('%d minute ago', '%d minutes ago', $mins, 'furik'), $mins);
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'furik'), $hours);
    } elseif ($diff < 604800) {
        $days = round($diff / 86400);
        return sprintf(_n('%d day ago', '%d days ago', $days, 'furik'), $days);
    } else {
        return date_i18n(get_option('date_format'), $time);
    }
}

/**
 * Enhanced styles for detailed layout
 */
function furik_donor_feed_enhanced_styles() {
    return '<style>
        .furik-donor-feed-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
        }
        .donor-feed-item-detailed {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f8f8;
            border-radius: 6px;
            transition: background 0.3s ease;
            flex-wrap: wrap;
            gap: 10px;
        }
        .donor-feed-item-detailed:hover {
            background: #efefef;
        }
        .donor-feed-item-detailed .donor-name {
            font-weight: 500;
            flex: 1;
            min-width: 150px;
        }
        .donor-feed-item-detailed .donor-amount {
            font-weight: bold;
            color: #2c3e50;
        }
        .donor-feed-item-detailed .donor-time {
            font-size: 0.85em;
            color: #666;
        }
        .donor-feed-item-detailed .donor-message {
            width: 100%;
            font-size: 0.9em;
            color: #555;
            font-style: italic;
            padding-top: 8px;
            border-top: 1px solid #e0e0e0;
            margin-top: 8px;
        }
    </style>';
}

/**
 * Clear cache when a new donation is made
 */
function furik_clear_donor_feed_cache($post_id = null) {
    global $wpdb;

    // Clear all donor feed transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_furik_donor_feed_%'
         OR option_name LIKE '_transient_timeout_furik_donor_feed_%'"
    );
}

// Hook to clear cache when transactions are updated
add_action('furik_transaction_completed', 'furik_clear_donor_feed_cache');
add_action('furik_transaction_status_changed', 'furik_clear_donor_feed_cache');

// Register shortcode
add_shortcode('furik_donor_feed', 'furik_shortcode_donor_feed');
