<?php
/**
 * MediaWiki Wikilog extension
 * Copyright © 2008 Juliano F. Ravasi
 * http://www.mediawiki.org/wiki/Extension:Wikilog
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

/**
 * @addtogroup Extensions
 * @author Juliano F. Ravasi < dev juliano info >
 */

if ( !defined( 'MEDIAWIKI' ) )
	die();


class WikilogCommentsPage extends Article {
	protected $mWikilogName;
	protected $mWikilogTitle;
	protected $mItemName;
	protected $mItemTitle;

	function __construct( &$title, &$wi ) {
		parent::__construct( $title );

		$this->mWikilogName = $wi->getName();
		$this->mWikilogTitle =& $wi->getTitle();

		if ( $wi->isItem() ) {
			$this->mItemName = $wi->getItemName();
			$this->mItemTitle = $wi->getItemTitle();
		} else {
			$this->mItemName = NULL;
			$this->mItemTitle = NULL;
		}
	}

	function view() {
		global $wgOut;
		wfLoadExtensionMessages( 'Wikilog' );

		# Comments page header
		if ( $this->mItemTitle !== NULL ) {
			$headerTxt = wfMsgExt( 'wikilog-comments-header',
				array( 'parse', 'content' ),
				/* $1 */ $this->mWikilogTitle->getPrefixedURL(),
				/* $2 */ $this->mWikilogName,
				/* $3 */ $this->mItemTitle->getPrefixedURL(),
				/* $4 */ $this->mItemName
			);
			if ( !empty( $headerTxt ) ) {
				$wgOut->addHtml( $headerTxt );
			}
		}

		parent::view();

		# Comments page footer
		if ( $this->mItemTitle !== NULL ) {
			$footerTxt = wfMsgExt( 'wikilog-comments-footer',
				array( 'parse', 'content' ),
				/* $1 */ $this->mWikilogTitle->getPrefixedURL(),
				/* $2 */ $this->mWikilogName,
				/* $3 */ $this->mItemTitle->getPrefixedURL(),
				/* $4 */ $this->mItemName
			);
			if ( !empty( $footerTxt ) ) {
				$wgOut->addHtml( $footerTxt );
			}
		}
	}

	function getNumComments() {
		if ( $this->exists() ) {
			$text = $this->fetchContent( 0, false );
			$num = preg_match_all( '/^ *==([^=].+?)== *$|(^|\n)-----*/m', $text, $m );

			if ( !empty( $m[2][0] ) ) {
				# First comment doesn't have title
				$num++;
			}
		} else {
			$num = 0;
		}

		return $num;
	}

}
