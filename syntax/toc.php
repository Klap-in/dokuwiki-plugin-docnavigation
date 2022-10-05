<?php
/**
 * DokuWiki Plugin docnav (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Gerrit Uitslag <klapinklapin@gmail.com>
 */

/**
 * Syntax for including a table of content of bundle of pages linked by docnavigation
 */
class syntax_plugin_docnavigation_toc extends DokuWiki_Syntax_Plugin
{

    /**
     * Syntax Type
     *
     * Needs to return one of the mode types defined in $PARSER_MODES in parser.php
     *
     * @return string
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * Paragraph Type
     *
     * Defines how this syntax is handled regarding paragraphs. This is important
     * for correct XHTML nesting. Should return one of the following:
     *
     * 'normal' - The plugin can be used inside paragraphs
     * 'block'  - Open paragraphs need to be closed before plugin output
     * 'stack'  - Special case. Plugin wraps other paragraphs.
     *
     * @return string
     * @see Doku_Handler_Block
     *
     */
    public function getPType()
    {
        return 'block';
    }

    /**
     * Sort for applying this mode
     *
     * @return int
     */
    public function getSort()
    {
        return 150;
    }

    /**
     * @param string $mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('<doctoc\b.*?>', $mode, 'plugin_docnavigation_toc');
    }

    /**
     * Handler to prepare matched data for the rendering process
     *
     * @param string $match The text matched by the patterns
     * @param int $state The lexer state for the match
     * @param int $pos The character position of the matched text
     * @param Doku_Handler $handler The Doku_Handler object
     * @return  array Return an array with all data you want to use in render, false don't add an instruction
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        global $ID;

        $optstrs = substr($match, 7, -1); // remove "<doctoc"  and ">"
        $optstrs = explode(',', $optstrs);
        $options = [
            'start' => $ID,
            'previous' => null, //needed for Include Plugin
            'includeheadings' => false,
            'numbers' => false,
            'useheading' => useHeading('navigation'),
            'hidepagelink' => false
        ];
        foreach ($optstrs as $optstr) {
            list($key, $value) = array_pad(explode('=', $optstr, 2), 2, '');
            $value = trim($value);

            switch (trim($key)) {
                case 'start':
                    $options['start'] = $this->getFullPageid($value);
                    $options['previous'] = $ID; //workaround for Include plugin: gets only correct ID in handler
                    break;
                case 'includeheadings':
                    [$start, $end] = array_pad(explode('-', $value, 2), 2, '');
                    $start = (int)$start;
                    $end = (int)$end;

                    if ($start < 1) {
                        $start = 2;
                    }

                    if ($end < 1) {
                        $end = $start;
                    }

                    //order from low to high
                    if ($start > $end) {
                        $level = $end;
                        $end = $start;
                        $start = $level;
                    }
                    $options['includeheadings'] = [$start, $end];
                    break;
                case 'numbers':
                    $options['numbers'] = !empty($value);
                    break;
                case 'useheading':
                    $options['useheading'] = !empty($value);
                    break;
                case 'hidepagelink':
                    $options['hidepagelink'] = !empty($value);
                    break;
            }
        }
        if ($options['hidepagelink'] && $options['includeheadings'] === false) {
            $options['includeheadings'] = [1, 2];
        }
        return $options;
    }

    /**
     * Handles the actual output creation.
     *
     * @param string $format output format being rendered
     * @param Doku_Renderer $renderer the current renderer object
     * @param array $options data created by handler()
     * @return  boolean                 rendered correctly? (however, returned value is not used at the moment)
     */
    public function render($format, Doku_Renderer $renderer, $options)
    {
        global $ID;
        global $ACT;

        if ($format != 'xhtml') return false;
        /** @var Doku_Renderer_xhtml $renderer */

        $renderer->nocache();

        $list = [];
        $recursioncheck = []; //needed 'hidepagelink' option
        $pageid = $options['start'];
        $previouspage = $options['previous'];
        while ($pageid !== null) {
            $pageitem = [];
            $pageitem['id'] = $pageid;
            $pageitem['ns'] = getNS($pageitem['id']);
            $pageitem['type'] = $options['includeheadings'] === false ? 'pageonly' : 'pagewithheadings'; //page or heading
            $pageitem['level'] = 1;
            $pageitem['ordered'] = $options['numbers'];

            if ($options['useheading']) {
                $pageitem['title'] = p_get_first_heading($pageitem['id'], METADATA_DONT_RENDER);
            } else {
                $pageitem['title'] = null;
            }
            $pageitem['perm'] = auth_quickaclcheck($pageitem['id']);

            if ($pageitem['perm'] >= AUTH_READ) {

                if ($options['hidepagelink']) {
                    $tocitemlevel = 1;
                    //recursive check needs a list of added pages
                    $recursioncheck[$pageid] = true;
                } else {
                    //add page to list
                    $list[$pageid] = $pageitem;
                    $tocitemlevel = 2;
                }

                if (!empty($options['includeheadings'])) {
                    $toc = p_get_metadata($pageid, 'description tableofcontents', METADATA_RENDER_USING_CACHE | METADATA_RENDER_UNLIMITED);

                    $first = true;
                    if (is_array($toc)) foreach ($toc as $tocitem) {
                        if ($tocitem['level'] < $options['includeheadings'][0] || $tocitem['level'] > $options['includeheadings'][1]) {
                            continue;
                        }
                        $item = [];
                        $item['id'] = $pageid . '#' . $tocitem['hid'];
                        $item['ns'] = getNS($item['id']);
                        if ($options['hidepagelink'] && $first) {
                            //mark only first heading(=title), if no pages are shown
                            $item['type'] = 'firstheading';
                            $first = false;
                        } else {
                            $item['type'] = 'heading';
                        }

                        $item['level'] = $tocitemlevel + $tocitem['level'] - $options['includeheadings'][0];
                        $item['title'] = $tocitem['title'];

                        $list[$item['id']] = $item;
                    }
                }
            }

            if ($ACT == 'preview' && $pageid === $ID) {
                // the RENDERER_CONTENT_POSTPROCESS event is triggered just after rendering the instruction,
                // so syntax instance will exists
                /** @var syntax_plugin_docnavigation_pagenav $pagenav */
                $pagenav = plugin_load('syntax', 'docnavigation_pagenav');
                if ($pagenav) {
                    $pagedata = $pagenav->data[$pageid];
                } else {
                    $pagedata = [];
                }
            } else {
                $pagedata = p_get_metadata($pageid, 'docnavigation');
            }

            //check referer
            if (empty($pagedata['previous']['link']) || $pagedata['previous']['link'] != $previouspage) {

                // is not first page or non-existing page (so without syntax)?
                if ($previouspage !== null && page_exists($pageid)) {
                    msg(sprintf($this->getLang('dontlinkback'), $pageid, $previouspage), -1);
                }
            }

            $previouspage = $pageid;
            $nextpageid = $pagedata['next']['link'];
            if (empty($nextpageid)) {
                $pageid = null;
            } elseif ($options['hidepagelink'] ? isset($recursioncheck[$nextpageid]) : isset($list[$nextpageid])) {
                msg(sprintf($this->getLang('recursionprevented'), $pageid, $nextpageid), -1);
                $pageid = null;
            } else {
                $pageid = $nextpageid;
            }
        }

        $renderer->doc .= html_buildlist($list, 'pagnavtoc', [$this, 'listItemNavtoc']);

        return true;
    }

    /**
     * Index item formatter
     *
     * User function for html_buildlist()
     *
     * @param array $item
     * @return string
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     */
    public function listItemNavtoc($item)
    {
        // default is noNSorNS($id), but we want noNS($id) when useheading is off FS#2605
        if ($item['title'] === null) {
            $name = noNS($item['id']);
        } else {
            $name = $item['title'];
        }

        $ret = '';
        $link = html_wikilink(':' . $item['id'], $name);
        if ($item['type'] == 'pagewithheadings' || $item['type'] == 'firstheading') {
            $ret .= '<strong>';
            $ret .= $link;
            $ret .= '</strong>';
        } else {
            $ret .= $link;
        }
        return $ret;
    }

    /**
     * Callback for html_buildlist
     *
     * @param array $item
     * @return string html
     */
    function html_list_toc($item)
    {
        if (isset($item['hid'])) {
            $link = '#' . $item['hid'];
        } else {
            $link = $item['link'];
        }

        return '<a href="' . $link . '">' . hsc($item['title']) . '</a>';
    }

    /**
     * Resolves given id against current page to full pageid, removes hash
     *
     * @param string $pageid
     * @return mixed
     */
    public function getFullPageid($pageid)
    {
        global $ID;
        // Igor and later
        if (class_exists('dokuwiki\File\PageResolver')) {
            $resolver = new dokuwiki\File\PageResolver($ID);
            $pageid = $resolver->resolveId($pageid);
        } else {
            // Compatibility with older releases
            resolve_pageid(getNS($ID), $pageid, $exists);
        }
        [$page, /* $hash */] = array_pad(explode('#', $pageid, 2), 2, '');
        return $page;
    }

}
