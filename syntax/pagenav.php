<?php
/**
 * DokuWiki Plugin DocNavigation (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Gerrit Uitslag <klapinklapin@gmail.com>
 */

/**
 * Handles document navigation syntax
 */
class syntax_plugin_docnavigation_pagenav extends DokuWiki_Syntax_Plugin
{

    /**
     * Stores data of navigation per page (for preview)
     *
     * @var array with entries:
     *   'previous' => [
     *      'link' => string,
     *      'title'  => null|string,
     *      'rawlink' => string
     *   ],
     *   'toc' => [...],
     *   'next' => [...]
     *
     */
    public $data = [];

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
        $this->Lexer->addSpecialPattern('<-[^\n]*\^[^\n]*\^[^\n]*->', $mode, 'plugin_docnavigation_pagenav');
        $this->Lexer->addSpecialPattern('<<[^\n]*\^[^\n]*\^[^\n]*>>', $mode, 'plugin_docnavigation_pagenav');
    }

    /**
     * Handler to prepare matched data for the rendering process
     *
     * Usually you should only need the $match param.
     *
     * @param string $match The text matched by the patterns
     * @param int $state The lexer state for the match
     * @param int $pos The character position of the matched text
     * @param Doku_Handler $handler The Doku_Handler object
     * @return  array Return an array with all data you want to use in render, false don't add an instruction
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        global $conf, $ID;

        // links are: 0=previous, 1=toc, 2=next
        $linkstrs = explode("^", substr($match, 2, -2), 3);
        $links = [];
        foreach ($linkstrs as $index => $linkstr) {
            // Split title from URL
            [$link, $title] = array_pad(explode('|', $linkstr, 2), 2, null);
            if (isset($title) && preg_match('/^\{\{[^}]+}}$/', $title)) {
                // If the title is an image, convert it to an array containing the image details
                $title = Doku_Handler_Parse_Media($title);
            }

            $link = trim($link);

            //look for an existing headpage when toc is empty
            if ($index == 1 && empty($link)) {
                $ns = getNS($ID);
                if (page_exists($ns . ':' . $conf['start'])) {
                    // start page inside namespace
                    $link = $ns . ':' . $conf['start'];
                } elseif (page_exists($ns . ':' . noNS($ns))) {
                    // page named like the NS inside the NS
                    $link = $ns . ':' . noNS($ns);
                } elseif (page_exists($ns)) {
                    // page like namespace exists
                    $link = (!getNS($ns) ? ':' : '') . $ns;
                }
            }
            //store original link with special chars and upper cases
            $rawlink = $link;

            // resolve and clean up the $id
            // Igor and later
            if (class_exists('dokuwiki\File\PageResolver')) {
                $resolver = new dokuwiki\File\PageResolver($ID);
                $link = $resolver->resolveId($link);
            } else {
                // Compatibility with older releases
                resolve_pageid(getNS($ID), $link, $exists);
            }
            //ignore hash
            [$link,$hash] = array_pad(explode('#', $link, 2), 2, '');

            //previous or next should not point to itself
            if ($index !== 1 && $link == $ID) {
                $link = '';
            }

            $links[] = [
                'link' => $link,
                'title' => $title,
                'rawlink' => $rawlink,
                'hash' => $hash
            ];
        }

        $data = [
            'previous' => $links[0],
            'toc' => $links[1],
            'next' => $links[2]
        ];

        // store data for preview
        $this->data[$ID] = $data;

        // return instruction data for renderers
        return $data;
    }

    /**
     * Handles the actual output creation.
     *
     * @param string $format output format being rendered
     * @param Doku_Renderer $renderer the current renderer object
     * @param array $data data created by handler()
     * @return  boolean                 rendered correctly? (however, returned value is not used at the moment)
     */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        if ($format == 'metadata') {
            /** @var Doku_Renderer_metadata $renderer */
            $renderer->meta['docnavigation'] = $data;

            foreach ($data as $url) {
                if ($url) {
                    if ($url['title'] === null) {
                        $defaulttitle = $renderer->_simpleTitle($url['rawlink']);
                        $url['title'] = $renderer->_getLinkTitle(null, $defaulttitle, $url['link']);
                    }
                    $renderer->internallink($url['link'], $url['title']);
                }
            }
            return true;
        }

        return false;
    }
}
