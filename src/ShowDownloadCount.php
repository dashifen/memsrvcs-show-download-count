<?php

namespace Dashifen\MemSrvcs\ShowDownloadCount;

use WP_Query;
use Dashifen\WPHandler\Handlers\HandlerException;
use Dashifen\WPHandler\Handlers\Plugins\AbstractPluginHandler;

class ShowDownloadCount extends AbstractPluginHandler
{
    /**
     * initialize
     *
     * Uses addAction() and addFilter() to connect WordPress to the methods
     * of this object's child which are intended to be protected.
     *
     * @return void
     * @throws HandlerException
     */
    public function initialize (): void
    {
        if (!$this->isInitialized()) {
            $this->addAction('admin_enqueue_scripts', 'addAssets');
            
            // these hooks all enhance the list view for attachments make it
            // possible to quickly see which ones have been downloaded.
            
            $this->addFilter('manage_media_columns', 'addCountColumn');
            $this->addAction('manage_media_custom_column', 'fillCountColumn', 10, 2);
            $this->addAction('restrict_manage_posts', 'addDownloadedFilter');
            $this->addAction('parse_query', 'filterDownloaded');
            $this->addFilter('posts_where', 'fixUndownloadedClause');
            
            // and these two let us reset the count, probably only useful
            // during testing, but maybe someone will want to do it at some
            // other time.
            
            $this->addAction('admin_head', 'addCountResetButton');
            $this->addAction('wp_ajax_reset-download-count', 'ajaxResetDownloadCount');
        }
    }
    
    /**
     * addAssets
     *
     * Adds the minimum of CSS and JS that this plugin needs to do its work.
     *
     * @return void
     */
    protected function addAssets (): void
    {
        $this->enqueue('assets/memsrvcs-show-download-count.js');
        $this->enqueue('assets/memsrvcs-show-download-count.css');
    }
    
    /**
     * addCountColumn
     *
     * Tactically, we're breaking the rules here.  We both add the downloads
     * column and remove the comments one.  This is because no on leaves
     * comments on media anyway.
     *
     * @param array $columns
     *
     * @return array
     */
    protected function addCountColumn (array $columns): array
    {
        $columns['downloads'] = '<span class="dashicons dashicons-download" title="Download Count"></span><span class="screen-reader-text">Download Count</span>';
        unset($columns['comments']);
        return $columns;
    }
    
    /**
     * fillCountColumn
     *
     * Prints the download count into the column prepared above.  If there is
     * no metadata related to downloads, it prints an empty string.
     *
     * @param string $column
     * @param int    $postId
     *
     * @return void
     */
    protected function fillCountColumn (string $column, int $postId): void
    {
        if ($column === 'downloads') {
            $count = get_post_meta($postId, '_download-count', true);
            echo !empty($count) ? $count : '';
        }
    }
    
    /**
     * addDownloadedFilter
     *
     * Adds a filter for download status (i.e., has a media file been
     * downloaded or not).
     *
     * @param string $type
     */
    protected function addDownloadedFilter (string $type): void
    {
        if ($type === 'attachment') {
            $current = $_GET['filter-downloaded'] ?? ''; ?>

          <label for="filter-downloaded" class="screen-reader-text">
            Filter by Downloaded Status
          </label>

          <select id="filter-downloaded" name="filter-downloaded">
            <option value="">All Media</option>
            <option value="yes"<?= $current === 'yes' ? ' selected' : '' ?>>Downloaded Media</option>
            <option value="no"<?= $current === 'no' ? ' selected' : '' ?>>Undownloaded Media</option>
          </select>
        <?php }
    }
    
    /**
     * filterDownloaded
     *
     * It's not enough to add the <select> element to create our filter above.
     * We also have to tell WP core what to do with it.  That's what this
     * method does.
     *
     * @param WP_Query $query
     */
    protected function filterDownloaded (WP_Query $query): void
    {
        if ($this->isDownloadedFilter($query)) {
            
            // if we're in here then we're querying attachments and the
            // filter for our downloaded media is either yes or no.  based
            // on that value, we'll determine how we filter.
            
            if ($_GET['filter-downloaded'] === 'yes') {
                
                // if we want to those attachments with a download count, we
                // want the ones where the post meta exists and is not zero.
                // we can make a meta query that does that.
                
                $metaQuery = [
                    'relationship' => 'AND',
                    [
                        'key'     => '_download-count',
                        'compare' => 'EXISTS'
                    ],
                    [
                        'key'     => '_download-count',
                        'compare' => '!=',
                        'value'   => '0',
                    ]
                ];
            } else {
                
                // for those that are not downloaded, we want the ones where
                // the post meta key doesn't exist or it is zero.  a meta query
                // can do that for us, too.
                
                $metaQuery = [
                    'relationship' => 'OR',
                    [
                        'key'     => '_download-count',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => '_download-count',
                        'compare' => '=',
                        'value'   => '0',
                    ]
                ];
            }
            
            $query->set('meta_query', $metaQuery);
        }
    }
    
    /**
     * isDownloadedFilter
     *
     * Returns true if this is our filter for downloaded attachments.
     *
     * @param WP_Query $query
     *
     * @return bool
     */
    private function isDownloadedFilter (WP_Query $query): bool
    {
        return $query->get('post_type') === 'attachment'
            && ($_GET['filter-downloaded'] ?? '') !== '';
    }
    
    /**
     * fixUndownloadedClause
     *
     * WP Core doesn't build our undownloaded media filter correctly even
     * thought it looks good above.  So, we fix it here.
     *
     * @param string $where
     *
     * @return string
     */
    protected function fixUndownloadedClause (string $where): string
    {
        global $wp_query;
        if ($this->isDownloadedFilter($wp_query)) {
            
            // for an unknown reason, on WP 5.3.4 the specification of the OR
            // relationship for our undownloaded filter is coming up as an AND
            // when the WP_Query builds its query even though it's clearly
            // specified as an OR relationship above.  so, here we fix that
            // but making sure our $where has only spaces for whitespace and
            // replacing the offending AND with an OR "by hand."
            
            $where = preg_replace('/\s+/', ' ', $where);
            $where = str_replace('AND mt1.post_id', 'OR mt1.post_id', $where);
        }
        
        return $where;
    }
    
    /**
     * addCountResetButton
     *
     * Adds a button to reset the media download counts on the media settings
     * page.
     *
     * @return void
     */
    protected function addCountResetButton (): void
    {
        $buttonPrinter = function () {
            echo <<< BUTTON
              <button class="button button-secondary" id="reset-download-count">
                Reset Download Counts
              </button>
            BUTTON;
        };
        
        add_settings_field(
            'download-count-reset-button',
            'Reset Download Counts',
            $buttonPrinter,
            'media',
            'uploads'
        );
    }
    
    /**
     * ajaxResetDownloadCount
     *
     * This method catches our request to reset download counts and then does
     * so.
     *
     * @return void
     * @noinspection SqlNoDataSourceInspection
     * @noinspection SqlResolve
     */
    protected function ajaxResetDownloadCount (): void
    {
        global $wpdb;
        
        // we could select only those posts with a download count and then loop
        // over them to reset their counts to zero individually, or we could
        // do it all in one fell swoop with an UPDATE query.  we'll opt for
        // that since it's a very straightforward query to write ourselves.  we
        // could use the wpdb update method, but it sucks.
        
        $sql = "UPDATE $wpdb->postmeta SET meta_value = %d WHERE meta_key = %s";
        $statement = $wpdb->prepare($sql, 0, '_download-count');
        $wpdb->query($statement);
        die;
    }
}
