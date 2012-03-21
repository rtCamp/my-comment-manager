<?php
/*
Plugin Name: My Comments Manager
Plugin URI: http://wpveda.com/my-comments-manager-wordpress-plugin/
Description: My Comments Manager is useful for multiauthor blogs where each author can manage comments posted on his/her articles. S/He can see all comments on one screen to which s/he hasn't replied. It also allows you to ignore the comments from your displayed comment list.
Version: 1.3
Author: rtCamp
Author URI: http://rtcamp.com/

*/
if (isset($_GET['ignore'])) {
    $rt_get_ignore_id = (int)$_GET['ignore'];
    rt_ignore_comment($rt_get_ignore_id);
}

if (isset ($_GET['unignore'])) {
    $rt_get_unignore_id = (int)$_GET['unignore'];
    rt_remove_ignore_comment($rt_get_unignore_id);
}



add_filter('comment_status_links', 'rt_cm_link');
/**
 * This function puts the Unreplied link in the edit comment page.
 *
 * @return $status_links which contains the link parameter.
 */
function rt_cm_link($status_links = array() ) {
    $status = "unreplied";
    if ( $status == $comment_status )
        $class = ' class="current"';
    $link = "/edit-comments.php?comment_status=" . $status;
    $status_links[] = "<li><a href=\"edit-comments.php?page=my_comment_manager&comment_status=unreplied\"$class>" . sprintf(
            __('Unreplied Comments On My Posts', 'rt_cm' ) . ' (%s)',rt_count_unreplied_comments()) . '</a>';
    return $status_links;
}



/**
 * This function adds the ignore link in array format.
 *
 * @return $status_links which contains the link parameter.
 */
function rt_cm_reply_link($status_links = array()) {
    global $comment;
    $status = $comment->comment_ID;
    $comment_status = isset($_REQUEST['comment_status']) ? $_REQUEST['comment_status'] : 'all';
    $status_links[] = "<a href=\"edit-comments.php?page=my_comment_manager&comment_status=$comment_status&ignore=$status\"$class>" . sprintf(
            __('Ignore')) . '</a>';
    return $status_links;
}
/* added js for comment....  pragati sureka*/
wp_enqueue_script('admin-comments');


add_action('admin_menu', 'rt_admin_page');
/**
 * This function puts submenu under Comment in admin menu.
 *
 */
function rt_admin_page() {
    $rt_awaiting_mod = rt_count_unreplied_comments();
    add_submenu_page('edit-comments.php', 'My Comments Manager', sprintf( __('My Comments Manager %s'), "<span id='awaiting-mod' class='count-$rt_awaiting_mod'><span class='pending-count'>" . number_format_i18n($rt_awaiting_mod) . "</span></span>" ), 'delete_posts', 'my_comment_manager', 'rt_comment_manager' );
}

/**
 * This function is responsible for the Rt Comments Manager page.
 *
 */
function rt_comment_manager() {
    global $wpdb, $user_ID;
    ?>
<div class="wrap">
        <?php
        $title = "My Comments Manager";
        screen_icon();?>
    <h2><?php echo esc_html( $title );
            if ( isset($_GET['s']) && $_GET['s'] )
                printf( '<span class="subtitle">' . sprintf( __( 'Search results for &#8220;%s&#8221;' ), wp_html_excerpt( esc_html( stripslashes( $_GET['s'] ) ), 50 ) ) . '</span>' ); ?>
    </h2>

        <?php
        $rt_comment_status = isset($_REQUEST['comment_status']) ? $_REQUEST['comment_status'] : 'all';
        if ( !in_array($rt_comment_status, array('all', 'moderated', 'approved', 'spam', 'trash', 'unreplied', 'ignored')) ) {
            $rt_comment_status = 'all';
        }
        $rt_mode = ( ! isset($_GET['mode']) || empty($_GET['mode']) ) ? 'detail' : esc_attr($_GET['mode']);
        $rt_search = isset($_GET['s']) ? $_GET['s'] : '';
        $rt_comments_per_page = 10;
        $rt_page = isset($_GET['apage']) ? $_GET['apage'] : 1;
        $rt_start = ( $rt_page - 1 ) * $rt_comments_per_page;
        $rt_comment_type = 'comment';
        $rt_post_id = isset($_REQUEST['p']) ? (int) $_REQUEST['p'] : 0;
        list($_rt_comments, $rt_total) = rt_get_comment_list($rt_comment_status, $rt_search, $rt_start, $rt_comments_per_page, $rt_post_id, $rt_comment_type);
        $rt_comments = array_slice($_rt_comments, 0, $rt_comments_per_page);
        $page_links = paginate_links( array(
                'base' => add_query_arg( 'apage', '%#%' ),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => ceil($rt_total / $rt_comments_per_page),
                'current' => $rt_page
        ));
        ?>
    <form id="comments-form" action="" method="get">
        <ul class="subsubsub">
                <?php
                $rt_status_links = array();
                $num_comments = ( $rt_post_id ) ? rt_count_comments( $rt_post_id ) : rt_count_comments();
                $user_can = current_user_can('edit_post', $post->ID);
                if ($user_can) {
                    $stati = array(
                            'all' => _n_noop('All', 'All'),
                            'moderated' => _n_noop('Pending <span class="count">(<span class="pending-count">%s</span>)</span>', 'Pending <span class="count">(<span class="pending-count">%s</span>)</span>'),
                            'approved' => _n_noop('Approved', 'Approved'),
                            'spam' => _n_noop('Spam <span class="count">(<span class="spam-count">%s</span>)</span>', 'Spam <span class="count">(<span class="spam-count">%s</span>)</span>'),
                            'trash' => _n_noop('Trash <span class="count">(<span class="trash-count">%s</span>)</span>', 'Trash <span class="count">(<span class="trash-count">%s</span>)</span>'),
                            'unreplied' => _n_noop('Unreplied Comment <span class="count">(<span class="unreplied-count">%s</span>)</span>', 'Unreplied Comments <span class="count">(<span class="unreplied-count">%s</span>)</span>'),
                            'ignored' => _n_noop('Ignored Comment <span class="count">(<span class="ignored-count">%s</span>)</span>', 'Ignored Comments <span class="count">(<span class="ignored-count">%s</span>)</span>')
                    );
                }
                else {
                    $stati = array(
                            'all' => _n_noop('All', 'All'),
                            'unreplied' => _n_noop('Unreplied Comment <span class="count">(<span class="unreplied-count">%s</span>)</span>', 'Unreplied Comments <span class="count">(<span class="unreplied-count">%s</span>)</span>'),
                            'ignored' => _n_noop('Ignored Comment <span class="count">(<span class="ignored-count">%s</span>)</span>', 'Ignored Comments <span class="count">(<span class="ignored-count">%s</span>)</span>')
                    );
                }

                if ( !EMPTY_TRASH_DAYS )
                    unset($stati['trash']);

                $link = 'edit-comments.php?page=my_comment_manager';
                if ( !empty($rt_comment_type) && 'all' != $rt_comment_type )
                    $link = add_query_arg( 'comment_type', $rt_comment_type, $link );

                foreach ( $stati as $status => $label ) {
                    $class = '';

                    if ( $status == $rt_comment_status )
                        $class = ' class="current"';
                    if ( !isset( $num_comments->$status ) )
                        $num_comments->$status = 10;
                    $link = add_query_arg( 'comment_status', $status, $link );
                    if ( $rt_post_id )
                        $link = add_query_arg( 'p', absint( $rt_post_id ), $link );

                    $rt_status_links[] = "<li class='$status'><a href='$link'$class>" . sprintf(
                            _n( $label[0], $label[1], $num_comments->$status ),
                            number_format_i18n( $num_comments->$status )
                            ) . '</a>';
                }
                echo implode( " |</li>\n", $rt_status_links) . '</li>';
                unset($rt_status_links);
                ?>
        </ul>
        <p class="search-box">
            <label class="screen-reader-text" for="comment-search-input"><?php _e( 'Search Comments' ); ?>:</label>
            <input type="text" id="comment-search-input" name="s" value="<?php _admin_search_query(); ?>" />
            <input type="hidden" name="page" value="my_comment_manager">
            <input type="submit" value="<?php esc_attr_e( 'Search Comments' ); ?>" class="button" />
        </p>
        <input type="hidden" name="mode" value="<?php echo esc_attr($rt_mode); ?>" />
            <?php if ( $rt_post_id ) : ?>
        <input type="hidden" name="p" value="<?php echo esc_attr( intval( $rt_post_id ) ); ?>" />
            <?php endif; ?>
        <input type="hidden" name="comment_status" value="<?php echo esc_attr($rt_comment_status); ?>" />
        <input type="hidden" name="pagegen_timestamp" value="<?php echo esc_attr(current_time('mysql', 1)); ?>" />
        <div class="tablenav">
                <?php if ( $page_links ) : ?>
            <div class="tablenav-pages"><?php $page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
                                number_format_i18n( $rt_start + 1 ),
                                number_format_i18n( min( $rt_page * $rt_comments_per_page, $rt_total ) ),
                                '<span class="total-type-count">' . number_format_i18n( $rt_total ) . '</span>',
                                $page_links
                        );
                        echo $page_links_text; ?></div>
            <input type="hidden" name="_total" value="<?php echo esc_attr($rt_total); ?>" />
            <input type="hidden" name="_per_page" value="<?php echo esc_attr($rt_comments_per_page); ?>" />
            <input type="hidden" name="_page" value="<?php echo esc_attr($rt_page); ?>" />
                <?php endif; ?>
            <br class="clear" />

        </div>
            <?php if ( $rt_comments ) {?>
        <table class="widefat comments fixed" cellspacing="0">
            <thead>
                <tr>
                            <?php print_column_headers('edit-comments'); ?>
                </tr>
            </thead>

            <tfoot>
                <tr>
                            <?php print_column_headers('edit-comments', false); ?>
                </tr>
            </tfoot>
            <tbody id="the-comment-list" class="list:comment">
                        <?php
                        foreach ($rt_comments as $rt_comment)
                            rt_comment_row( $rt_comment->comment_ID, $rt_mode, $rt_comment_status );
                        ?>
            </tbody>
        </table>
        <div class="tablenav">
                    <?php
                    if ( $page_links )
                        echo "<div class='tablenav-pages'>$page_links_text</div>";
                    ?>
        </div>
    </form>
</div>
        <?php
    }
    else {
        echo "No Results Found!";
    }
    if (current_user_can('moderate_comments')) {
        wp_comment_reply('-1', true, 'detail');
        wp_comment_trashnotice();
    }
    else {
        rt_comment_reply('-1', true, 'detail');
    }
}

/**
 * This function handles the display comment reply fields for authors.
 *
 */
function rt_comment_reply($position = '1', $checkbox = false, $mode = 'single', $table_row = true) {
    global $current_user;

    // allow plugin to replace the popup content
    $content = apply_filters( 'wp_comment_reply', '', array('position' => $position, 'checkbox' => $checkbox, 'mode' => $mode) );

    if ( ! empty($content) ) {
        echo $content;
        return;
    }

    $columns = get_column_headers('edit-comments');
    $hidden = array_intersect( array_keys( $columns ), array_filter( get_hidden_columns('edit-comments') ) );
    $col_count = count($columns) - count($hidden);

    ?>
<form method="get" action="">
    <?php if ( $table_row ) : ?>
    <table style="display:none;"><tbody id="com-reply"><tr id="replyrow" style="display:none;"><td colspan="<?php echo $col_count; ?>">
    <?php else : ?>
                    <div id="com-reply" style="display:none;"><div id="replyrow" style="display:none;">
    <?php endif; ?>
                            <div id="replyhead" style="display:none;"><?php _e('Reply to Comment'); ?></div>

                            <div id="edithead" style="display:none;">
                                <div class="inside">
                                    <label for="author"><?php _e('Name') ?></label>
                                    <input type="text" name="newcomment_author" size="50" value="" tabindex="101" id="author" />
                                </div>

                                <div class="inside">
                                    <label for="author-email"><?php _e('E-mail') ?></label>
                                    <input type="text" name="newcomment_author_email" size="50" value="" tabindex="102" id="author-email" />
                                </div>

                                <div class="inside">
                                    <label for="author-url"><?php _e('URL') ?></label>
                                    <input type="text" id="author-url" name="newcomment_author_url" size="103" value="" tabindex="103" />
                                </div>
                                <div style="clear:both;"></div>
                            </div>

                            <div id="replycontainer"><textarea rows="8" cols="40" name="replycontent" tabindex="104" id="replycontent"></textarea></div>

                            <p id="replysubmit" class="submit">
                                <a href="#comments-form" class="cancel button-secondary alignleft" tabindex="106"><?php _e('Cancel'); ?></a>
                                <a href="#comments-form" class="save button-primary alignright" tabindex="104">
                                    <span id="rtreplybtn"><?php _e('Submit Reply'); ?></span></a>
                                <img class="waiting" style="display:none;" src="images/wpspin_light.gif" alt="" />
                                <span class="error" style="display:none;"></span>
                                <br class="clear" />
                            </p>

                            <input type="hidden" name="user_ID" id="user_ID" value="<?php echo $current_user->ID; ?>" />
                            <input type="hidden" name="action" id="action" value="" />
                            <input type="hidden" name="comment_ID" id="comment_ID" value="" />
                            <input type="hidden" name="comment_post_ID" id="comment_post_ID" value="" />
                            <input type="hidden" name="status" id="status" value="" />
                            <input type="hidden" name="position" id="position" value="<?php echo $position; ?>" />
                            <input type="hidden" name="checkbox" id="checkbox" value="<?php echo $checkbox ? 1 : 0; ?>" />
                            <input type="hidden" name="mode" id="mode" value="<?php echo esc_attr($mode); ?>" />
    <?php wp_nonce_field( 'rt-reply-to-comment', '_ajax_nonce', false ); ?>
    <?php wp_comment_form_unfiltered_html_nonce(); ?>
    <?php if ( $table_row ) : ?>
                            </td></tr></tbody></table>
    <?php else : ?>
                        </div></div>
    <?php endif; ?>
                    </form>
                    <script type="text/javascript">
                        jQuery("#rtreplybtn").click(function(){
                            var post = {};

                            jQuery('#replysubmit .waiting').show();

                            jQuery('#replyrow input').each(function() {
                                post[ jQuery(this).attr('name') ] = jQuery(this).val();
                            });

                            post.content = jQuery('#replycontent').val();
                            post.id = post.comment_post_ID;
                            post.comments_listing = this.comments_listing;
                            post['action'] = "rt-reply-to-comment";

                            jQuery.ajax({
                                type : 'POST',
                                url : ajaxurl,
                                data : post,
                                success : function(x) { commentReply.show(x); },
                                error : function(r) { commentReply.error(r); }
                            });

                            return false;
                        });
                    </script>
    <?php
}

add_action('wp_ajax_rt-reply-to-comment', 'rt_reply_to_comment');
/**
 * This function handles the comment reply of authors.
 *
 */
function rt_reply_to_comment() {
    global $wpdb, $user_ID;
    $action = $_POST['action'];
    check_ajax_referer( $action );

    $comment_post_ID = (int) $_POST['comment_post_ID'];

    $status = $wpdb->get_var( $wpdb->prepare("SELECT post_status FROM $wpdb->posts WHERE ID = %d", $comment_post_ID) );
    if ( empty($status) )
        die('1');
    elseif ( in_array($status, array('draft', 'pending', 'trash') ) )
        die( __('Error: you are replying to a comment on a draft post.') );

    $user = wp_get_current_user();
    if ( $user->ID ) {
        $comment_author       = $wpdb->escape($user->display_name);
        $comment_author_email = $wpdb->escape($user->user_email);
        $comment_author_url   = $wpdb->escape($user->user_url);
        $comment_content      = trim($_POST['content']);
        if ( current_user_can('unfiltered_html') ) {
            if ( wp_create_nonce('unfiltered-html-comment_' . $comment_post_ID) != $_POST['_wp_unfiltered_html_comment'] ) {
                kses_remove_filters(); // start with a clean slate
                kses_init_filters(); // set up the filters
            }
        }
    } else {
        die( __('Sorry, you must be logged in to reply to a comment.') );
    }

    if ( '' == $comment_content )
        die( __('Error: please type a comment.') );

    $comment_parent = absint($_POST['comment_ID']);
    $commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_type', 'comment_parent', 'user_ID');
    $comment_id = wp_new_comment( $commentdata );
    $comment = get_comment($comment_id);

    if ( ! $comment ) die('1');

    $modes = array( 'single', 'detail', 'dashboard' );
    $mode = isset($_POST['mode']) && in_array( $_POST['mode'], $modes ) ? $_POST['mode'] : 'detail';
    $position = ( isset($_POST['position']) && (int) $_POST['position']) ? (int) $_POST['position'] : '-1';
    $checkbox = ( isset($_POST['checkbox']) && true == $_POST['checkbox'] ) ? 1 : 0;

    if ( get_option('show_avatars') && 'single' != $mode )
        add_filter( 'comment_author', 'floated_admin_avatar' );

    $x = new WP_Ajax_Response();

    ob_start();
    if ( 'dashboard' == $mode ) {
        require_once( ABSPATH . 'wp-admin/includes/dashboard.php' );
        _wp_dashboard_recent_comments_row( $comment, false );
    } else {
        rt_comment_row( $comment->comment_ID, $mode, false, $checkbox );
    }
    $comment_list_item = ob_get_contents();
    ob_end_clean();

    $x->add( array(
            'what' => 'comment',
            'id' => $comment->comment_ID,
            'data' => $comment_list_item,
            'position' => $position
    ));

    $x->send();
}

/**
 * This function counts the number of unreplied comments in the user's posts.
 *
 * @return $unreplied_total which contains the number of unreplied comments.
 */
function rt_count_unreplied_comments() {
    list($rt_unreplied_comment_id, $unreplied_total) = rt_unreplied_comments();
    return $unreplied_total;
}

/**
 * This function gets the unreplied comments.
 *
 * @return array($rt_unreplied_comment_id, $unreplied_total) which contains the unreplied comment ids and total unreplied comments.
 */
function rt_unreplied_comments() {
    global $wpdb, $user_ID;
    $rt_post_sql = "SELECT ID FROM $wpdb->posts WHERE post_author=$user_ID AND post_status != 'trash'";
    $rt_posts = $wpdb->get_col($rt_post_sql);
    $rt_posts_ids = implode(',', $rt_posts);
    list($rt_ignore_comment_id, $ignored_total) = rt_ignored_comments();
    if ($ignored_total > 0) {
        $rt_comment_sql = "SELECT * FROM $wpdb->comments WHERE comment_post_ID IN ($rt_posts_ids) AND comment_ID NOT IN ({$rt_ignore_comment_id}) AND comment_type NOT IN ('trackback','pingback') ORDER BY comment_post_ID, comment_date_gmt DESC";
    }
    else {
        $rt_comment_sql = "SELECT * FROM $wpdb->comments WHERE comment_post_ID IN ($rt_posts_ids) AND comment_type NOT IN ('trackback','pingback') ORDER BY comment_post_ID, comment_date_gmt DESC";
    }
    $rt_comments = $wpdb->get_results($rt_comment_sql);
    $post_id = 0;
    $replied = false;
    $unreplied_comments = '';
    $unreplied_total = 0;

    foreach ($rt_comments as $comment) {
        if ($post_id != $comment->comment_post_ID) {
            $post_id = $comment->comment_post_ID;
            $replied = false;
            if (!($replied)) {
                if ($comment->user_id != $user_ID) {
                    $unreplied_comments .= $comment->comment_ID . ', ';
                    $unreplied_total++;
                }
                else {
                    $replied = true;
                }
            }
        }
    }
    $rt_unreplied_comment_id = substr($unreplied_comments, 0, -2);
    return array($rt_unreplied_comment_id, $unreplied_total);
}

/**
 * This function gets the ignored comments.
 *
 * @return array($rt_ignore_comment_ids, $rt_total_ignore) which contains the ignored comment ids and total ignored comments.
 */
function rt_ignored_comments() {
    global $wpdb, $user_ID;
    $rt_post_sql = "SELECT ID FROM {$wpdb->posts} WHERE post_author={$user_ID} AND post_status != 'trash'";
    $rt_posts = $wpdb->get_col($rt_post_sql);
    $rt_posts_ids = implode(',', $rt_posts);
    $rt_comments_sql = "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID IN ({$rt_posts_ids}) AND comment_type = ''";
    $rt_comments = $wpdb->get_col($rt_comments_sql);
    $rt_comments_ids = implode(',',$rt_comments);
    $meta_key = 'rt_comment_manger_ignore';
    $meta_value = 'ignore';
    $ignore_sql = "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key='{$meta_key}' AND meta_value='{$meta_value}' AND comment_id IN ({$rt_comments_ids})";
    $rt_ignore_comments = $wpdb->get_col($ignore_sql);
    $rt_total_ignore = count($rt_ignore_comments);
    $rt_ignore_comment_ids = implode(",", $rt_ignore_comments);
    return array($rt_ignore_comment_ids, $rt_total_ignore);
}

/**
 * This function gets the comment list.
 *
 * @return array($_rt_comments, $total) which contains the comments and total comments.
 */
function rt_get_comment_list($rt_comment_status = '', $rt_search = false, $rt_start, $rt_comments_per_page, $rt_post_id = 0, $rt_comment_type = '') {
    global $wpdb, $user_ID;
    $rt_start = abs((int)$rt_start);
    $rt_num = (int)$rt_comments_per_page;
    $rt_post_id = (int)$rt_post_id;
    $rt_count = rt_count_comments();
    $rt_index = '';
    list($rt_ignore_comment_id, $ignored_total) = rt_ignored_comments();

    if ( 'unreplied' == $rt_comment_status ) {
        list($rt_unreplied_comment_id, $unreplied_total) = rt_unreplied_comments();
        if ($unreplied_total > 0) {
            $approved = "c.comment_ID IN ({$rt_unreplied_comment_id}) AND ( c.comment_approved = '0' OR c.comment_approved = '1' )";
        }
        $total = $unreplied_total;
    } elseif ( 'ignored' == $rt_comment_status ) {
        if ($ignored_total > 0) {
            $approved = "c.comment_ID IN ({$rt_ignore_comment_id})";
        }
        $total = $ignored_total;
    } elseif ( 'moderated' == $rt_comment_status ) {
        $approved = "c.comment_approved = '0'";
        $total = $rt_count->moderated;
    } elseif ( 'approved' == $rt_comment_status ) {
        $approved = "c.comment_approved = '1'";
        $total = $rt_count->approved;
    } elseif ( 'spam' == $rt_comment_status ) {
        $approved = "c.comment_approved = 'spam'";
        $total = $rt_count->spam;
    } elseif ( 'trash' == $rt_comment_status ) {
        $approved = "c.comment_approved = 'trash'";
        $total = $rt_count->trash;
    } else {
        $approved = "( c.comment_approved = '0' OR c.comment_approved = '1' )";
        $total = $rt_count->moderated + $rt_count->approved;
        $rt_index = 'USE INDEX (c.comment_date_gmt)';
    }

    if (($ignored_total > 0) && ('ignored' != $rt_comment_status)) {
        $approved .= " AND c.comment_ID NOT IN ({$rt_ignore_comment_id})";
    }

    if ( $rt_post_id ) {
        $total = '';
        $post = " AND c.comment_post_ID = {$rt_post_id}";
    } else {
        $total = $rt_count->moderated + $rt_count->approved;
        $rt_post_sql = "SELECT ID FROM {$wpdb->posts} WHERE post_author={$user_ID} AND post_status != 'trash'";
        $rt_posts = $wpdb->get_col($rt_post_sql);
        $rt_posts_ids = implode(',', $rt_posts);
        $post = "AND c.comment_post_ID IN ({$rt_posts_ids})";
    }

    $orderby = "ORDER BY c.comment_date_gmt DESC LIMIT $rt_start, $rt_num";

    if ( 'comment' == $rt_comment_type )
        $typesql = "AND c.comment_type = ''";
    elseif ( 'pings' == $rt_comment_type )
        $typesql = "AND ( c.comment_type = 'pingback' OR c.comment_type = 'trackback' )";
    elseif ( 'all' == $rt_comment_type )
        $typesql = '';
    elseif ( !empty($rt_comment_type) )
        $typesql = $wpdb->prepare("AND c.comment_type = %s", $rt_comment_type);
    else
        $typesql = '';

    if ( !empty($rt_comment_type) )
        $total = '';

    $query = "FROM $wpdb->comments c LEFT JOIN $wpdb->posts p ON c.comment_post_ID = p.ID WHERE p.post_status != 'trash' ";
    if ( $rt_search ) {
        $total = '';
        $rt_search = $wpdb->escape($rt_search);
        $query .= "AND
			(c.comment_author LIKE '%$rt_search%' OR
			c.comment_author_email LIKE '%$rt_search%' OR
			c.comment_author_url LIKE ('%$rt_search%') OR
			c.comment_author_IP LIKE ('%$rt_search%') OR
			c.comment_content LIKE ('%$rt_search%') ) AND
                $approved
                $typesql";
    } else {
        $query .= "AND $approved $post $typesql";
    }
    $_rt_comments = $wpdb->get_results("SELECT * $query $orderby");
    if ( '' === $total )
        $total = $wpdb->get_var("SELECT COUNT(c.comment_ID) $query");
    return array($_rt_comments, $total);
}

/**
 * This function counts the comments.
 *
 * @return $stats which contains count of comments.
 */
function rt_count_comments($rt_post_id = 0) {
    global $wpdb, $user_ID;
    $rt_post_id = (int)$rt_post_id;
    $rt_post_sql = "SELECT ID FROM {$wpdb->posts} WHERE post_author={$user_ID} AND post_status != 'trash'";
    $rt_posts = $wpdb->get_col($rt_post_sql);
    $rt_posts_ids = implode(',', $rt_posts);
    $rt_where = '';
    if ($rt_post_id > 0 ) {
        $where = $wpdb->prepare("WHERE comment_post_ID = %d", $rt_post_id);
        $rt_count = $wpdb->get_results("SELECT comment_approved, COUNT( * ) AS num_comments FROM {$wpdb->comments} {$rt_where} GROUP BY comment_approved", ARRAY_A);
    }
    else {
        $rt_count = $wpdb->get_results( "SELECT comment_approved, COUNT( * ) AS num_comments FROM {$wpdb->comments} WHERE comment_post_ID IN ({$rt_posts_ids}) GROUP BY comment_approved", ARRAY_A);
    }
    $rt_total = 0;
    $rt_approved = array('0' => 'moderated', '1' => 'approved', 'spam' => 'spam', 'trash' => 'trash', 'post-trashed' => 'post-trashed', 'unreplied' => 'unreplied', 'ignored' => 'ignored');
    $rt_known_types = array_keys($rt_approved);
    foreach( (array) $rt_count as $row_num => $row ) {
        if ( 'post-trashed' != $row['comment_approved'] && 'trash' != $row['comment_approved'] )
            $rt_total += $row['num_comments'];
        if ( in_array( $row['comment_approved'], $rt_known_types ) )
            $stats[$rt_approved[$row['comment_approved']]] = $row['num_comments'];
    }
    list($rt_ignore_comment_id, $ignored_total) = rt_ignored_comments();
    $stats[$rt_approved['unreplied']] = rt_count_unreplied_comments();
    $stats[$rt_approved['ignored']] = $ignored_total;
    $stats['total_comments'] = $rt_total;
    foreach ( $rt_approved as $key ) {
        if ( empty($stats[$key]) )
            $stats[$key] = 0;
    }
    $stats = (object)$stats;
    return $stats;
}

/**
 * This function displays the comment list.
 *
 */
function rt_comment_row( $comment_id, $mode, $comment_status, $checkbox = true, $from_ajax = false ) {
    global $comment, $post, $_comment_pending_count;
    $comment = get_comment( $comment_id );
    $post = get_post($comment->comment_post_ID);
    $the_comment_status = wp_get_comment_status($comment->comment_ID);
    $user_can = current_user_can('edit_post', $post->ID);

    $author_url = get_comment_author_url();
    if ( 'http://' == $author_url )
        $author_url = '';
    $author_url_display = preg_replace('|http://(www\.)?|i', '', $author_url);
    if ( strlen($author_url_display) > 50 )
        $author_url_display = substr($author_url_display, 0, 49) . '...';

    $ptime = date('G', strtotime( $comment->comment_date ) );
    if ( ( abs(time() - $ptime) ) < 86400 )
        $ptime = sprintf( __('%s ago'), human_time_diff( $ptime ) );
    else
        $ptime = mysql2date(__('Y/m/d \a\t g:i A'), $comment->comment_date );

    if ( $user_can ) {
        $del_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "delete-comment_$comment->comment_ID" ) );
        $approve_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "approve-comment_$comment->comment_ID" ) );

        $approve_url = esc_url( "comment.php?action=approvecomment&p=$post->ID&c=$comment->comment_ID&$approve_nonce" );
        $unapprove_url = esc_url( "comment.php?action=unapprovecomment&p=$post->ID&c=$comment->comment_ID&$approve_nonce" );
        $spam_url = esc_url( "comment.php?action=spamcomment&p=$post->ID&c=$comment->comment_ID&$del_nonce" );
        $unspam_url = esc_url( "comment.php?action=unspamcomment&p=$post->ID&c=$comment->comment_ID&$del_nonce" );
        $trash_url = esc_url( "comment.php?action=trashcomment&p=$post->ID&c=$comment->comment_ID&$del_nonce" );
        $untrash_url = esc_url( "comment.php?action=untrashcomment&p=$post->ID&c=$comment->comment_ID&$del_nonce" );
        $delete_url = esc_url( "comment.php?action=deletecomment&p=$post->ID&c=$comment->comment_ID&$del_nonce" );
    }
    $ignore_url = esc_url( "edit-comments.php?page=my_comment_manager&comment_status=$comment_status&ignore=$comment->comment_ID");
    $unignore_url = esc_url( "edit-comments.php?page=my_comment_manager&comment_status=$comment_status&unignore=$comment->comment_ID");

    echo "<tr id='comment-$comment->comment_ID' class='$the_comment_status'>";
    $columns = get_column_headers('edit-comments');
    $hidden = get_hidden_columns('edit-comments');
    foreach ( $columns as $column_name => $column_display_name ) {
        $class = "class=\"$column_name column-$column_name\"";

        $style = '';
        if ( in_array($column_name, $hidden) )
            $style = ' style="display:none;"';

        $attributes = "$class$style";

        switch ($column_name) {
            case 'cb':
                if ( !$checkbox ) break;
                echo '<th scope="row" class="check-column">';
                if ( $user_can ) echo "<input type='checkbox' name='delete_comments[]' value='$comment->comment_ID' />";
                echo '</th>';
                break;
            case 'comment':
                echo "<td $attributes>";
                echo '<div id="submitted-on">';
                printf(__('Submitted on <a href="%1$s">%2$s at %3$s</a>'), get_comment_link($comment->comment_ID), get_comment_date(__('Y/m/d')), get_comment_date(__('g:ia')));
                echo '</div>';
                comment_text();
                if ( $user_can ) { ?>
<div id="inline-<?php echo $comment->comment_ID; ?>" class="hidden">
    <textarea class="comment" rows="1" cols="1"><?php echo htmlspecialchars( apply_filters('comment_edit_pre', $comment->comment_content), ENT_QUOTES ); ?></textarea>
    <div class="author-email"><?php echo esc_attr( $comment->comment_author_email ); ?></div>
    <div class="author"><?php echo esc_attr( $comment->comment_author ); ?></div>
    <div class="author-url"><?php echo esc_attr( $comment->comment_author_url ); ?></div>
    <div class="comment_status"><?php echo $comment->comment_approved; ?></div>
</div>
                    <?php
                }

                if ( $user_can ) {
                    // preorder it: Approve | Reply | Quick Edit | Edit | Spam | Trash
                    $actions = array(
                            'approve' => '', 'unapprove' => '',
                            'reply' => '', 'ignore' => '', 'unignore' => '',
                            'quickedit' => '',
                            'edit' => '',
                            'spam' => '', 'unspam' => '',
                            'trash' => '', 'untrash' => '', 'delete' => ''
                    );

                    if ( $comment_status && 'all' != $comment_status ) {
                        if ( 'approved' == $the_comment_status )
                            $actions['unapprove'] = "<a href='$unapprove_url' class='delete:the-comment-list:comment-$comment->comment_ID:e7e7d3:action=dim-comment&amp;new=unapproved vim-u vim-destructive' title='" . __( 'Unapprove this comment' ) . "'>" . __( 'Unapprove' ) . '</a>';
                        else if ( 'unapproved' == $the_comment_status )
                            $actions['approve'] = "<a href='$approve_url' class='delete:the-comment-list:comment-$comment->comment_ID:e7e7d3:action=dim-comment&amp;new=approved vim-a vim-destructive' title='" . __( 'Approve this comment' ) . "'>" . __( 'Approve' ) . '</a>';
                    } else {
                        $actions['approve'] = "<a href='$approve_url' class='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=approved vim-a' title='" . __( 'Approve this comment' ) . "'>" . __( 'Approve' ) . '</a>';
                        $actions['unapprove'] = "<a href='$unapprove_url' class='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=unapproved vim-u' title='" . __( 'Unapprove this comment' ) . "'>" . __( 'Unapprove' ) . '</a>';
                    }

                    if ( 'spam' != $the_comment_status && 'trash' != $the_comment_status && 'ignored' != $comment_status ) {
                        $actions['spam'] = "<a href='$spam_url' class='delete:the-comment-list:comment-$comment->comment_ID::spam=1 vim-s vim-destructive' title='" . __( 'Mark this comment as spam' ) . "'>" . /* translators: mark as spam link */ _x( 'Spam', 'verb' ) . '</a>';
                    } elseif ( 'spam' == $the_comment_status ) {
                        $actions['unspam'] = "<a href='$untrash_url' class='delete:the-comment-list:comment-$comment->comment_ID:66cc66:unspam=1 vim-z vim-destructive'>" . __( 'Not Spam' ) . '</a>';
                    } elseif ( 'trash' == $the_comment_status ) {
                        $actions['untrash'] = "<a href='$untrash_url' class='delete:the-comment-list:comment-$comment->comment_ID:66cc66:untrash=1 vim-z vim-destructive'>" . __( 'Restore' ) . '</a>';
                    }

                    if ( 'spam' == $the_comment_status || 'trash' == $the_comment_status || !EMPTY_TRASH_DAYS || 'ignored' == $comment_status ) {
                        $actions['delete'] = "<a href='$delete_url' class='delete:the-comment-list:comment-$comment->comment_ID::delete=1 delete vim-d vim-destructive'>" . __('Delete Permanently') . '</a>';
                    } else {
                        $actions['trash'] = "<a href='$trash_url' class='delete:the-comment-list:comment-$comment->comment_ID::trash=1 delete vim-d vim-destructive' title='" . __( 'Move this comment to the trash' ) . "'>" . _x('Trash', 'verb') . '</a>';
                    }

                    if ( 'trash' != $the_comment_status ) {
                        if ( 'ignored' != $comment_status) {
                            $actions['edit'] = "<a href='comment.php?action=editcomment&amp;c={$comment->comment_ID}' title='" . __('Edit comment') . "'>". __('Edit') . '</a>';
                            $actions['quickedit'] = '<a onclick="commentReply.open(\''.$comment->comment_ID.'\',\''.$post->ID.'\',\'edit\');return false;" class="vim-q" title="'.__('Quick Edit').'" href="#">' . __('Quick&nbsp;Edit') . '</a>';
                            if ( 'spam' != $the_comment_status ) {
                                $actions['reply'] = '<a onclick="commentReply.open(\''.$comment->comment_ID.'\',\''.$post->ID.'\');return false;" class="vim-r" title="'.__('Reply to this comment').'" href="#">' . __('Reply') . '</a>';
                                //$actions['ignore'] = "<a href='$ignore_url' class='rtignore' title='" . __( 'Ignore this comment' ) . "'>" . _x('Ignore', 'verb') . '</a>';
                            }
                        }
                        else {
                            $actions['unignore'] = "<a href='$unignore_url' class='rtunignore' title='" . __( 'Remove this comment from Ignore list' ) . "'>" . _x('Remove', 'verb') . '</a>';
                        }
                    }
                    $actions = apply_filters( 'comment_row_actions', array_filter($actions), $comment );

                    $i = 0;
                    echo '<div class="row-actions">';
                    foreach ( $actions as $action => $link ) {
                        ++$i;
                        ( ( ('approve' == $action || 'unapprove' == $action) && 2 === $i ) || 1 === $i ) ? $sep = '' : $sep = ' | ';

                        if ( ('reply' == $action || 'quickedit' == $action) && ! $from_ajax )
                            $action .= ' hide-if-no-js';
                        elseif ( ($action == 'untrash' && $the_comment_status == 'trash') || ($action == 'unspam' && $the_comment_status == 'spam') ) {
                            if ('1' == get_comment_meta($comment_id, '_wp_trash_meta_status', true))
                                $action .= ' approve';
                            else
                                $action .= ' unapprove';
                        }

                        echo "<span class='$action'>$sep$link</span>";
                    }
                    echo '</div>';
                }
                else {
                    // preorder it: Approve | Reply | Quick Edit | Edit | Spam | Trash
                    $actions = array(
                            'reply' => '', 'ignore' => '', 'unignore' => ''
                    );
                    if ( 'trash' != $the_comment_status ) {
                        if ( 'ignored' != $comment_status) {
                            if ( 'spam' != $the_comment_status ) {
                                $actions['reply'] = '<a onclick="commentReply.open(\''.$comment->comment_ID.'\',\''.$post->ID.'\');return false;" class="vim-r" title="'.__('Reply to this comment').'" href="#">' . __('Reply') . '</a> | ';
                                //$actions['ignore'] = "<a href='$ignore_url' class='rtignore' title='" . __( 'Ignore this comment' ) . "'>" . _x('Ignore', 'verb') . '</a>';
                            }
                        }
                        else {
                            $actions['unignore'] = "<a href='$unignore_url' class='rtunignore' title='" . __( 'Remove this comment from Ignore list' ) . "'>" . _x('Remove', 'verb') . '</a>';
                        }
                    }
                    $actions = apply_filters( 'comment_row_actions', array_filter($actions), $comment );

                    $i = 0;
                    echo '<div class="row-actions">';
                    foreach ( $actions as $action => $link ) {
                        ++$i;
                        if ( ('reply' == $action) && ! $from_ajax )
                            $action .= ' hide-if-no-js';

                        echo "<span class='$action'>$link</span>";
                    }
                    echo '</div>';
                }

                echo '</td>';
                break;
            case 'author':
                echo "<td $attributes><strong>";
                comment_author();
                echo '</strong><br />';
                if ( !empty($author_url) )
                    echo "<a title='$author_url' href='$author_url'>$author_url_display</a><br />";
                if ( $user_can ) {
                    if ( !empty($comment->comment_author_email) ) {
                        comment_author_email_link();
                        echo '<br />';
                    }
                    echo '<a href="edit-comments.php?s=';
                    comment_author_IP();
                    echo '&amp;mode=detail';
                    if ( 'spam' == $comment_status )
                        echo '&amp;comment_status=spam';
                    echo '">';
                    comment_author_IP();
                    echo '</a>';
                }
                echo '</td>';
                break;
            case 'date':
                echo "<td $attributes>" . get_comment_date(__('Y/m/d \a\t g:ia')) . '</td>';
                break;
            case 'response':
                if ( 'single' !== $mode ) {
                    if ( isset( $_comment_pending_count[$post->ID] ) ) {
                        $pending_comments = absint( $_comment_pending_count[$post->ID] );
                    } else {
                        $_comment_pending_count_temp = (array) get_pending_comments_num( array( $post->ID ) );
                        $pending_comments = $_comment_pending_count[$post->ID] = $_comment_pending_count_temp[$post->ID];
                    }
                    if ( $user_can ) {
                        $post_link = "<a href='" . get_edit_post_link($post->ID) . "'>";
                        $post_link .= get_the_title($post->ID) . '</a>';
                    } else {
                        $post_link = get_the_title($post->ID);
                    }
                    echo "<td $attributes>\n";
                    echo '<div class="response-links"><span class="post-com-count-wrapper">';
                    echo $post_link . '<br />';
                    $pending_phrase = sprintf( __('%s pending'), number_format( $pending_comments ) );
                    if ( $pending_comments )
                        echo '<strong>';
                    comments_number("<a href='edit-comments.php?p=$post->ID' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . /* translators: comment count link */ _x('0', 'comment count') . '</span></a>', "<a href='edit-comments.php?p=$post->ID' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . /* translators: comment count link */ _x('1', 'comment count') . '</span></a>', "<a href='edit-comments.php?p=$post->ID' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . /* translators: comment count link: % will be substituted by comment count */ _x('%', 'comment count') . '</span></a>');
                    if ( $pending_comments )
                        echo '</strong>';
                    echo '</span> ';
                    echo "<a href='" . get_permalink( $post->ID ) . "'>#</a>";
                    echo '</div>';
                    if ( 'attachment' == $post->post_type && ( $thumb = wp_get_attachment_image( $post->ID, array(80, 60), true ) ) )
                        echo $thumb;
                    echo '</td>';
                }
                break;
            default:
                echo "<td $attributes>\n";
                do_action( 'manage_comments_custom_column', $column_name, $comment->comment_ID );
                echo "</td>\n";
                break;
        }
    }
    echo "</tr>\n";
}

/**
 * This function ignores the comment.
 * @param $rt_comment_id is the ID of the comment which is to be ignored
 *
 */
function rt_ignore_comment($rt_comment_id) {
    $rt_ignore_comment_id = (int)$rt_comment_id;
    if ($rt_ignore_comment_id > 0) {
        $meta_key = 'rt_comment_manger_ignore';
        $meta_value = 'ignore';
        update_comment_meta($rt_ignore_comment_id, $meta_key, $meta_value);
    }
}

/**
 * This function removes the comment from ignore list.
 * @param $rt_comment_id is the ID of the comment which is to be removed from ignored list.
 *
 */
function rt_remove_ignore_comment($rt_comment_id) {
    $rt_ignore_comment_id = (int)$rt_comment_id;
    if ($rt_ignore_comment_id > 0) {
        $meta_key = 'rt_comment_manger_ignore';
        delete_comment_meta($rt_ignore_comment_id, $meta_key);
    }
}

$comment_status = isset($_REQUEST['comment_status']) ? $_REQUEST['comment_status'] : 'all';
$rt_check_page = isset($_GET['page']) ? $_GET['page'] : null;
if ((($comment_status == 'all') || ($comment_status == 'moderated') || ($comment_status == 'approved') || ($comment_status == 'unreplied'))) {
    add_filter('comment_row_actions', 'rt_cm_reply_link');
}
?>
