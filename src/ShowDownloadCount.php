<?php

namespace Dashifen\MemSrvcs\ShowDownloadCount;

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
            $this->addFilter('manage_media_columns', 'addCountColumn');
            $this->addAction('manage_media_custom_column', 'fillCountColumn', 10 ,2);
        }
    }
    
    /**
     * addCountColumn
     *
     * Techically, we're breaking the rules here.
     *
     * @param array $columns
     *
     * @return array
     */
    protected function addCountColumn (array $columns): array
    {
        foreach ($columns as $column) {
            if ($column === 'comments') {
                $columns['downloads'] = '<span class="dashicons dashicons-download"></span><span class="screen-reader-text">Downloads</span>';
            }
        }
        
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
}
