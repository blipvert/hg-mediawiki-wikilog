<?php
/**
 * Better Feed classes for MediaWiki
 * Copyright © 2008 Juliano F. Ravasi
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

if ( !defined( 'MEDIAWIKI' ) )
	die();


/**
 * Syndication base class. This class shares common metadata for both feeds
 * (RSS channels) and entries (RSS items).
 */
abstract class WlSyndicationBase {

	/**
	 * A permanent, universally unique identifier for an entry or feed.
	 * Required. Corresponds to atom:id and rss:guid (entries only).
	 */
	protected $mId = null;

	/**
	 * A text construct that conveys a human-readable title for the entry
	 * or feed. Required. Corresponds to atom:title and rss:title elements.
	 * Assumed to be plain text unless it is an instance of WlTextConstruct.
	 * @note RSS doesn't support HTML in this field.
	 */
	protected $mTitle = null;

	/**
	 * References from an entry or feed to web resources. This array is
	 * indexed by the relationship type between the element and the resource.
	 * Each item is an array of links of that type, each item is an
	 * associative array of link attibutes. Corresponds to atom:link element
	 * of Atom, and rss:link, rss:enclosure, rss:source and rss:comments
	 * elements of RSS.
	 * @note RSS only supports a single instance of each of these elements.
	 */
	protected $mLinks = array();

	/**
	 * Authors of the entry or feed. Optional. Corresponds to atom:author
	 * element of Atom, and dc:creator element of RSS. It is an array of
	 * associative arrays with information about the author: 'name', 'uri'
	 * and 'email'.
	 * @note RSS only supports the name of each author.
	 * @see $mContributors
	 */
	protected $mAuthors = array();

	/**
	 * Contributors of the entry or feed. Optional. Corresponds to
	 * atom:contributor element of Atom. It is an array of associative
	 * arrays with information about the contributor: 'name', 'uri' and
	 * 'email'.
	 * @note RSS doesn't support this concept, thus it is omitted.
	 * @see $mAuthors
	 */
	protected $mContributors = array();

	/**
	 * Information about a category associated with an entry or feed.
	 * Corresponds to atom:category and rss:category elements.
	 */
	protected $mCategories = array();

	/**
	 * A text construct that conveys information about rights held in and
	 * over na entry or feed. Optional. Corresponds to atom:rights and
	 * rss:copyright (feed only) elements. Assumed to be plain text unless it
	 * is an instance of WlTextConstruct.
	 * @note RSS doesn't support HTML in this field.
	 * @note RSS only supports rights for feeds, not entries.
	 */
	protected $mRights = null;

	/**
	 * A date value indicating the most recent instant in time when an entry
	 * or feed was modified. Corresponds to atom:updated element of Atom and
	 * rss:lastBuildDate (feed) element of RSS. It is also used as rss:pubDate
	 * element of RSS if no published date is provided
	 * (see WlSyndicationEntry::$mPublished).
	 */
	protected $mUpdated = null;

	/**
	 * Language of the entry or feed. Corresponds to xml:lang attribute of
	 * the atom:feed and atom:entry elements of Atom, and rss:language (feed)
	 * element of RSS.
	 */
	protected $mLanguage = null;

	/**
	 * Constructor.
	 *
	 * @param $id Unique identifier URI. Required.
	 * @param $title Feed or entry title. Required.
	 * @param $updated Last updated date/time. If omitted, the current
	 *   date/time is assumed.
	 */
	function __construct( $id, $title, $updated = null ) {
		global $wgContLanguageCode;
		$this->mId = $id;
		$this->mTitle = $title;
		$this->mUpdated = $updated ? $updated : wfTimestampNow();
		$this->mLanguage = $wgContLanguageCode;
	}

	/**
	 * Returns the Unique identifier URI of the feed or entry.
	 */
	function getId() { return $this->mId; }

	function setTitle( $value ) { $this->mTitle = $title; }
	function getTitle() { return $this->mTitle; }

	function setLinks( $value ) { $this->mLinks = $value; }
	function getLinks() { return $this->mLinks; }
	
	function setAuthors( $value ) { $this->mAuthors = $value; }
	function getAuthors() { return $this->mAuthors; }

	function setContributors( $value ) { $this->mContributors = $value; }
	function getContributors() { return $this->mContributors; }

	function setCategories( $value ) { $this->mCategories = $value; }
	function getCategories() { return $this->mCategories; }

	function setRights( $value ) { $this->mRights = $value; }
	function getRights() { return $this->mRights; }

	function setUpdated( $value ) { $this->mUpdated = $value; }
	function getUpdated() { return $this->mUpdated; }

	function setLanguage( $value ) { $this->mLanguage = $value; }
	function getLanguage() { return $this->mLanguage; }

	function addLink( $href, $type = null, $hreflang = null, $title = null,
			$length = null ) {
		$link = array( 'href' => $href );
		if ( $type ) {
			$link['type'] = $type;
		}
		if ( $hreflang ) {
			$link['hreflang'] = $hreflang;
		}
		if ( $title ) {
			$link['title'] = $title;
		}
		if ( $length ) {
			$link['length'] = $length;
		}
		$this->addLinkRel( 'alternate', $link );
	}

	function addLinkRel( $rel, $link ) {
		$this->mLinks[$rel][] = $link;
	}

	function addAuthor( $name, $uri = null, $email = null ) {
		$this->mAuthors[] = array(
			'name'   => $name,
			'uri'    => $uri,
			'email'  => $email
		);
	}

	function addContributor( $name, $uri = null, $email = null ) {
		$this->mContributors[] = array(
			'name'   => $name,
			'uri'    => $uri,
			'email'  => $email
		);
	}

	function addCategory( $term, $label = null, $scheme = null ) {
		$this->mCategories[] = array(
			'term'   => $term,
			'label'  => $label,
			'scheme' => $scheme
		);
	}

}


/**
 * Syndication feed class. This class represents a feed (Atom) or channel
 * (RSS).
 */
abstract class WlSyndicationFeed extends WlSyndicationBase {

	/**
	 * A text construct that conveys a human-readable description or subtitle
	 * for the feed. Optional. Corresponds to atom:subtitle and rss:description
	 * elements. Assumed to be plain text unless it is an instance of
	 * WlTextConstruct.
	 * @note RSS doesn't support HTML in this field.
	 */
	protected $mSubtitle = null;

	/**
	 * IRI reference that identifies an image that provides iconic visual
	 * identification for a feed. Optional. Corresponds to atom:icon element of
	 * Atom.
	 * @note RSS doesn't support icons directly.
	 */
	protected $mIcon = null;

	/**
	 * IRI reference that identifies an image that provides visual
	 * identification for a feed. Optional. Corresponds to atom:logo and
	 * rss:image elements.
	 * @todo Fix discrepancies between atom:logo and rss:image.
	 */
	protected $mLogo = null;

	/**
	 * Quirks to workaround browser "misbehaviors". Mostly used for debugging.
	 */
	protected $mQuirks = array();

	/**
	 * String used to disable browser detection of feed.
	 */
	const QUIRK_SNIFF_STR =
		"512+ bytes of junk in order to avoid browser detection of feed data.\n";

	/**
	 * Constructor.
	 *
	 * @param $id Feed universally unique identifier. Required.
	 * @param $title Feed title. Required. Text or HTML (Atom only).
	 * @param $updated Last updated date/time. If omitted, the current
	 *   date/time is assumed.
	 * @param $url Feed URL, pointing back to the page it represents.
	 *   Optional.
	 */
	function __construct( $id, $title, $updated = null, $url = null ) {
		parent::__construct( $id, $title, $updated );

		if ( $url ) {
			global $wgMimeType;
			$this->addLink( $url, $wgMimeType );
		}

		global $wgRequest;
		$this->addLinkRel( 'self', array(
			'href' => $wgRequest->getFullRequestURL(),
			'type' => $this->defaultContentType()
		) );
	}

	function setSubtitle( $value ) { $this->mSubtitle = $value; }
	function getSubtitle() { return $this->mSubtitle; }

	function setIcon( $value ) { $this->mIcon = $value; }
	function getIcon() { return $this->mIcon; }

	function setLogo( $value ) { $this->mLogo = $value; }
	function getLogo() { return $this->mLogo; }

	/**
	 * Returns information about the feed generator (yes, MediaWiki), for
	 * filling into atom:generator and rss:generator elements.
	 * @return An associative array with 'attribs' and 'content' keys. The
	 *   'attribs' value is suitable for using as the attributes of the
	 *   atom:generator element, and contains 'uri' and 'version' attributes.
	 *   The 'content' value contains the name of the generator ("MediaWiki"),
	 *   and is suitable for the contents of atom:generator element.
	 */
	function getGenerator() {
		global $wgVersion;
		return array(
			'attribs' => array(
				'uri' => 'http://www.mediawiki.org/',
				'version' => $wgVersion
			),
			'content' => "MediaWiki"
		);
	}

	/**
	 * Disables OutputPage and returns the HTTP headers for the feed.
	 */
	function httpHeaders() {
		global $wgOut;

		# We take over from $wgOut, excepting its cache header info
		$wgOut->disable();
		$mimetype = $this->contentType();
		header( "Content-type: $mimetype; charset=UTF-8" );
		$wgOut->sendCacheControl();
	}

	/**
	 * Returns the content type for the feed. Depends on overloaded abstract
	 * method defaultContentType(). The query parameter ctype affects the
	 * MIME type returned, if the value is allowed.
	 * @return Feed content type.
	 */
	function contentType() {
		global $wgRequest;

		$default = $this->defaultContentType();
		$ctype = $wgRequest->getVal( 'ctype', $default );
		$allowedctypes = array(
			'application/atom+xml',
			'application/rss+xml',
			'application/xml',
			'text/xml'
		);
		return in_array( $ctype, $allowedctypes ) ? $ctype : $default;
	}

	/**
	 * Output the XML headers common to both Atom and RSS feeds (XML
	 * declaration and stylesheet.
	 */
	function outXmlHeader() {
		global $wgStylePath, $wgStyleVersion;
		global $wgRequest;

		$this->mQuirks = explode( ',', $wgRequest->getVal( 'quirks' ) );

		$this->httpHeaders();
		echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";

		if ( in_array( 'sniff', $this->mQuirks ) ) {
			echo "<!--\n" . str_repeat( self::QUIRK_SNIFF_STR, 10 ) . "-->\n";
		}
		
		if ( !in_array( 'style', $this->mQuirks ) ) {
			echo '<?xml-stylesheet type="text/css" href="' .
				htmlspecialchars( wfExpandUrl( "$wgStylePath/common/feed.css?$wgStyleVersion" ) ) .
				'"?' . ">\n";
		}
	}

	/**
	 * This function must be overloaded in derived classes, and must return
	 * the default content type for the feed.
	 * @return Default feed content type.
	 */
	abstract function defaultContentType();

	/**
	 * This function must be overloaded in derived classes, and must return
	 * the proper representation of the given timestamp.
	 * @param $ts MediaWiki-formatted timestamp.
	 * @return Feed-formatted date construct.
	 */
	abstract function formatTime( $ts );

	/**
	 * Output the header of the feed. This includes all the metadata about
	 * the feed, like id, title, links, updated, etc.
	 */
	abstract function outHeader();

	/**
	 * Output a single entry for the feed.
	 * @param $entry Entry.
	 */
	abstract function outEntry( WlSyndicationEntry $entry );

	/**
	 * Output the footer of the feed. This usually only closes tags left open
	 * from outHeader().
	 */
	abstract function outFooter();

}


/**
 * Syndication entry class. This class represents individual entries (Atom) or
 * items (RSS).
 */
class WlSyndicationEntry extends WlSyndicationBase {

	/**
	 * A date value indicating an instant in time when the entry was published.
	 */
	protected $mPublished = null;

	/**
	 * Source feed.
	 */
	protected $mSource = null;

	/**
	 * A text construct that conveys a short summary, abstract, or excerpt of
	 * an entry.
	 */
	protected $mSummary = null;

	/**
	 * A text construct that conveys the content of the entry.
	 */
	protected $mContent = null;

	/**
	 * Constructor.
	 *
	 * @param $id Entry universally unique identifier. Required.
	 * @param $title Entry title. Required. Text or HTML (Atom only).
	 * @param $updated Last updated date/time. If omitted, the current
	 *   date/time is assumed.
	 * @param $url Entry URL, pointing back to the page it represents.
	 *   Optional.
	 * @param $author Entry author. Optional.
	 * @param $content Entry content. Optional.
	 */
	function __construct( $id, $title, $updated = null, $url = null,
			$author = null, $content = null ) {
		parent::__construct( $id, $title, $updated );

		if ( $url ) {
			global $wgMimeType;
			$this->addLink( $url, $wgMimeType );
		}

		if ( $author ) {
			$this->addAuthor( $author );
		}

		if ( $content ) {
			$this->setContent( $content );
		}
	}

	function setPublished( $value ) { $this->mPublished = $value; }
	function getPublished() { return $this->mPublished; }

	function setSource( $value ) { $this->mSource = $value; }
	function getSource() { return $this->mSource; }

	function setSummary( $value ) { $this->mSummary = $value; }
	function getSummary() { return $this->mSummary; }

	function setContent( $value ) { $this->mContent = $value; }
	function getContent() { return $this->mContent; }

}


/**
 * A text construct. May contain plain text, HTML or XHTML.
 */
class WlTextConstruct {

	protected $mLang = null;
	protected $mType = null;
	protected $mText = null;

	function __construct( $type, $text, $lang = null ) {
		if ( $type != 'text' && $type != 'html' && $type != 'xhtml' ) {
			throw new MWException( __METHOD__ . ': Invalid text construct type.' );
		}

		$this->mType = $type;
		$this->mText = $text;
		$this->mLang = $lang;
	}

	function getLang() { return $this->mLang; }
	function getType() { return $this->mType; }

	function getText() {
		if ( $this->mType == 'text' ) {
			return $this->mText;
		} else {
			return strip_tags( $this->mText );
		}
	}

	function getHTML() {
		if ( $this->mType == 'text' ) {
			return htmlspecialchars( $this->mText );
		} else if ( $this->mType == 'html' || $this->mType == 'xhtml' ) {
			return $this->mText;
		}
	}

	function getXML( $element = null, $attribs = array() ) {
		if ( $this->mType == 'xhtml' ) {
			$content = Xml::tags( 'div', array( 'xmlns' => "http://www.w3.org/1999/xhtml" ), $this->mText );
		} else {
			$content = htmlspecialchars( $this->mText );
		}
		if ( $element ) {
			if ( $this->mLang ) {
				$attribs['xml:lang'] = $this->mLang;
			}
			$attribs['type'] = $this->mType;
			$content = Xml::tags( $element, $attribs, $content );
		}
		return $content;
	}

}


/**
 * Atom feed class.
 */
class WlAtomFeed extends WlSyndicationFeed {

	function defaultContentType() {
		return 'application/atom+xml';
	}

	function formatTime( $ts ) {
		return gmdate( 'Y-m-d\TH:i:s\Z', wfTimestamp( TS_UNIX, $ts ) );
	}

	function formatTextData( $element, $contents ) {
		if ( is_null( $contents ) ) {
			return null;
		} else if ( $contents instanceof WlTextConstruct ) {
			return $contents->getXML( $element ) . "\n";
		} else {
			return Xml::element( $element, null, $contents ) . "\n";
		}
	}

	function formatPersonData( $element, $person ) {
		$content = Xml::element( 'name', null, $person['name'] );
		if ( isset( $person['uri'] ) && !empty( $person['uri'] ) ) {
			$content .= Xml::element( 'uri', null, $person['uri'] );
		}
		if ( isset( $person['email'] ) && !empty( $person['email'] ) ) {
			$content .= Xml::element( 'email', null, $person['email'] );
		}
		return Xml::tags( $element, null, $content );
	}

	function outHeader() {
		$this->outXmlHeader();

		echo Xml::openElement( 'feed',
			array(
				'xmlns'     => "http://www.w3.org/2005/Atom",
				'xmlns:thr' => "http://purl.org/syndication/thread/1.0",
				'xml:lang'  => $this->getLanguage()
			)
		) . "\n";

		echo Xml::element( 'id', null, $this->getId() ) . "\n";
		echo $this->formatTextData( 'title', $this->getTitle() );
		echo $this->formatTextData( 'subtitle', $this->getSubtitle() );

		foreach ( $this->getLinks() as $rel => $links ) {
			foreach ( $links as $link ) {
				echo Xml::element( 'link', array( 'rel' => $rel ) + $link ) . "\n";
			}
		}

		foreach ( $this->getAuthors() as $author ) {
			echo $this->formatPersonData( 'author', $author ) . "\n";
		}

		foreach ( $this->getContributors() as $contributor ) {
			echo $this->formatPersonData( 'contributor', $contributor ) . "\n";
		}

		foreach ( $this->getCategories() as $category ) {
			echo Xml::element( 'category', $category ) . "\n";
		}

		if ( $this->getIcon() ) {
			echo Xml::element( 'icon', null, $this->getIcon() ) . "\n";
		}

		if ( $this->getLogo() ) {
			echo Xml::element( 'logo', null, $this->getLogo() ) . "\n";
		}

		echo Xml::element( 'updated', null,
			$this->formatTime( $this->getUpdated() ) ) . "\n";

		echo $this->formatTextData( 'rights', $this->getRights() );

		$gtor = $this->getGenerator();
		echo Xml::element( 'generator', $gtor['attribs'], $gtor['content'] ) . "\n";
	}

	function outEntry( WlSyndicationEntry $entry ) {
		echo Xml::openElement( 'entry' ) . "\n";

		echo Xml::element( 'id', null, $entry->getId() ) . "\n";
		echo $this->formatTextData( 'title', $entry->getTitle() );

		foreach ( $entry->getLinks() as $rel => $links ) {
			foreach ( $links as $link ) {
				echo Xml::element( 'link', array( 'rel' => $rel ) + $link ) . "\n";
			}
		}

		foreach ( $entry->getAuthors() as $author ) {
			echo $this->formatPersonData( 'author', $author ) . "\n";
		}

		foreach ( $entry->getContributors() as $contributor ) {
			echo $this->formatPersonData( 'contributor', $contributor ) . "\n";
		}

		foreach ( $entry->getCategories() as $category ) {
			echo Xml::element( 'category', $category ) . "\n";
		}

		echo Xml::element( 'published', null,
			$this->formatTime( $entry->getPublished() ) ) . "\n";

		echo Xml::element( 'updated', null,
			$this->formatTime( $entry->getUpdated() ) ) . "\n";

		echo $this->formatTextData( 'rights', $entry->getRights() );
		echo $this->formatTextData( 'summary', $entry->getSummary() );
		echo $this->formatTextData( 'content', $entry->getContent() );

		# !!TODO!!: echo $entry->getSource();

		echo Xml::closeElement( 'entry' ) . "\n";
	}

	function outFooter() {
		echo Xml::closeElement( 'feed' ) . "\n";
	}

}


/**
 * RSS feed class.
 */
class WlRSSFeed extends WlSyndicationFeed {

	function defaultContentType() {
		return 'application/rss+xml';
	}

	function formatTime( $ts ) {
		return gmdate( 'D, d M Y H:i:s \G\M\T', wfTimestamp( TS_UNIX, $ts ) );
	}

	function formatTextData( $element, $contents ) {
		if ( is_null( $contents ) ) {
			return null;
		} else if ( $contents instanceof WlTextConstruct ) {
			return Xml::element( $element, null, $contents->getText() ) . "\n";
		} else {
			return Xml::element( $element, null, $contents ) . "\n";
		}
	}

	function outHeader() {
		$this->outXmlHeader();

		echo Xml::openElement( 'rss',
			array(
				'version'       => "2.0",
				'xmlns:atom'    => "http://www.w3.org/2005/Atom",
				'xmlns:content' => "http://purl.org/rss/1.0/modules/content/",
				'xmlns:dc'      => "http://purl.org/dc/elements/1.1/"
			)
		) . "\n";

		echo Xml::openElement( 'channel' ) . "\n";
		echo $this->formatTextData( 'title', $this->getTitle() );
		echo $this->formatTextData( 'description', $this->getSubtitle() );

		foreach ( $this->getLinks() as $rel => $links ) {
			if ( $rel == 'alternate' ) {
				# RSS only supports (and requires) a single link element.
				$link = array_shift( $links );
				echo Xml::element( 'link', null, $link['href'] ) . "\n";
			} else {
				# For other links, we use the atom namespace.
				foreach ( $links as $link ) {
					echo Xml::element( 'atom:link', array( 'rel' => $rel ) + $link ) . "\n";
				}
			}
		}

		foreach ( $this->getAuthors() as $author ) {
			echo Xml::element( 'dc:creator', null, $author['name'] ) . "\n";
		}

		echo Xml::element( 'language', null, $this->getLanguage() ) . "\n";
		echo Xml::element( 'lastBuildDate', null, $this->formatTime( $this->getUpdated() ) ) . "\n";
		echo $this->formatTextData( 'copyright', $this->getRights() );
		
		$gtor = $this->getGenerator();
		echo Xml::element( 'generator', null, "{$gtor['content']} {$gtor['attribs']['version']}" ) . "\n";
	}

	function outEntry( WlSyndicationEntry $entry ) {
		echo Xml::openElement( 'item' ) . "\n";
		echo Xml::element( 'guid', array( 'isPermaLink' => "false" ), $entry->getId() ) . "\n";
		echo $this->formatTextData( 'title', $entry->getTitle() );

		foreach ( $entry->getLinks() as $rel => $links ) {
			if ( $rel == 'alternate' ) {
				if ( !empty( $links ) ) {
					# RSS only supports a single link element.
					$link = array_shift( $links );
					echo Xml::element( 'link', null, $link['href'] ) . "\n";
				}
			} else if ( $rel == 'enclosure' ) {
				if ( !empty( $links ) ) {
					# RSS only supports a single enclosure element.
					$link = array_shift( $links );
					$attribs = array(
						'url' => $link['href'],
						'type' => $link['type'],
						'length' => $link['length']
					);
					echo Xml::element( 'enclosure', $attribs ) . "\n";
				}
			} else if ( $rel == 'replies' ) {
				if ( !empty( $links ) ) {
					# RSS only supports a single comments element.
					$link = array_shift( $links );
					echo Xml::element( 'comments', null, $link['href'] ) . "\n";
				}
			} else if ( $rel == 'via' ) {
				if ( !empty( $links ) ) {
					# RSS only supports a single source element.
					$link = array_shift( $links );
					echo Xml::element( 'source', array( 'url' => $link['href'] ),
						$link['title'] ) . "\n";
				}
			} else {
				# For other links, we use the atom namespace.
				foreach ( $links as $link ) {
					echo Xml::element( 'atom:link', array( 'rel' => $rel ) + $link ) . "\n";
				}
			}
		}

		foreach ( $entry->getAuthors() as $author ) {
			echo Xml::element( 'dc:creator', null, $author['name'] ) . "\n";
		}

		# !!TODO!!: echo $entry->getCategories();

		# Use either published or updated dates for the pubDate element.
		$date = $entry->getPublished() ? $entry->getPublished() : $entry->getUpdated();
		echo Xml::element( 'pubDate', null, $this->formatTime( $date ) ) . "\n";

		# If only summary or only content is provided, prefer the standard
		# description element for either. If both are provided, put the
		# summary in the description element and the content in the extension
		# content:encoded element.
		$content = $description = null;
		if ( $entry->getSummary() && $entry->getContent() ) {
			$description = $entry->getSummary();
			$content = $entry->getContent();
		} else if ( $entry->getSummary() ) {
			$description = $entry->getSummary();
		} else if ( $entry->getContent() ) {
			$description = $entry->getContent();
		}
		
		if ( $description ) {
			if ( $description instanceof WlTextConstruct ) {
				echo Xml::element( 'description', null, $description->getHTML() );
			} else {
				echo Xml::element( 'description', null, htmlspecialchars( $description ) );
			}
		}

		if ( $content ) {
			if ( $content instanceof WlTextConstruct ) {
				echo Xml::element( 'content:encoded', null, $content->getHTML() );
			} else {
				echo Xml::element( 'content:encoded', null, htmlspecialchars( $content ) );
			}
		}

		echo Xml::closeElement( 'item' ) . "\n";
	}

	function outFooter() {
		echo Xml::closeElement( 'channel' ) . "\n";
		echo Xml::closeElement( 'rss' ) . "\n";
	}

}

