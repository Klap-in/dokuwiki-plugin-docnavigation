<?php
/**
 * DokuWiki Plugin DocNavigation (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Gerrit Uitslag <klapinklapin@gmail.com>
 */

/**
 * Add documentation navigation elements around page
 */
class action_plugin_docnavigation extends DokuWiki_Action_Plugin
{

    /**
     * Register the events
     *
     * @param Doku_Event_Handler $controller
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('RENDERER_CONTENT_POSTPROCESS', 'AFTER', $this, 'addtopnavigation');
    }

    /**
     * Add navigation bar to top of content
     *
     * @param Doku_Event $event
     */
    public function addtopnavigation(Doku_Event $event)
    {
        global $ACT;

        if ($event->data[0] != 'xhtml' || !in_array($ACT, ['show', 'preview'])) return;

        $event->data[1] = $this->htmlNavigationbar(false)
            . $event->data[1]
            . $this->htmlNavigationbar(true);
    }

    /**
     * Return html of navigation elements
     *
     * @param bool $linktoToC if true, add referer to ToC
     * @return string
     */
    private function htmlNavigationbar($linktoToC)
    {
        global $ID;
        global $ACT;
        $data = [];
        if ($ACT == 'preview') {
            // the RENDERER_CONTENT_POSTPROCESS event is triggered just after rendering the instruction,
            // so syntax instance will exists
            /** @var syntax_plugin_docnavigation_pagenav $pagenav */
            $pagenav = plugin_load('syntax', 'docnavigation_pagenav');
            if ($pagenav) {
                $data = $pagenav->data[$ID];
            }
        } else {
            $data = p_get_metadata($ID, 'docnavigation');
        }

        $out = '';
        if (!empty($data)) {
            /** @var Doku_Renderer_xhtml $Renderer */
            static $Renderer = null;
            if (is_null($Renderer)) {
                $Renderer = p_get_renderer('xhtml');
            }

            if ($linktoToC) {
                $out .= '<div class="clearer"></div>';
            }

            $out .= '<div class="docnavbar' . ($linktoToC ? ' showtoc' : '') . '"><div class="leftnav">';
            if ($data['previous']['link']) {
                $title = $this->getTitle($data['previous'], $Renderer);
                $out .= '← ' . $Renderer->internallink(':' . $data['previous']['link'], $title, null, true);
            }
            $out .= '&nbsp;</div>';

            if ($linktoToC) {
                $out .= '<div class="centernav">';
                if ($data['toc']['link']) {
                    $title = $this->getTitle($data['toc'], $Renderer);
                    $out .= $Renderer->internallink(':' . $data['toc']['link'], $title, null, true);
                }
                $out .= '&nbsp;</div>';
            }

            $out .= '<div class="rightnav">&nbsp;';
            if ($data['next']['link']) {
                $title = $this->getTitle($data['next'], $Renderer);
                $out .= $Renderer->internallink(':' . $data['next']['link'], $title, null, true) . ' →';
            }
            $out .= '</div></div>';
        }
        return $out;
    }

    /**
     * Build nice url title, if no title given use original link with original not cleaned id
     *
     * @param array $link with: 'link' => string full page id, 'title' => null|string, 'rawlink' => string original not cleaned id
     * @param Doku_Renderer_xhtml $Renderer
     * @return string
     */
    protected function getTitle($link, $Renderer)
    {
        if ($link['title'] === null) {
            $defaulttitle = $Renderer->_simpleTitle($link['rawtitle']);
            return $Renderer->_getLinkTitle(null, $defaulttitle, $isImage, $link['link']);
        }
        return $link['title'];
    }
}
