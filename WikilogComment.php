<?php
/**
 * MediaWiki Wikilog extension
 * Copyright © 2008, 2009 Juliano F. Ravasi
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


class WikilogComment
{
	const S_OK				= 'OK';
	const S_PENDING			= 'PENDING';
	const S_DELETED			= 'DELETED';

	public static $statusMap = array(
		self::S_OK				=> false,
		self::S_PENDING			=> 'pending',
		self::S_DELETED			=> 'deleted',
	);

	public  $mItem			= NULL;
	private $mTextChanged	= false;

	public  $mID			= NULL;
	public  $mParent		= NULL;
	public  $mThread		= NULL;
	public  $mUserID		= NULL;
	public  $mUserText		= NULL;
	public  $mAnonName		= NULL;
	public  $mStatus		= NULL;
	public  $mTimestamp		= NULL;
	public  $mUpdated		= NULL;
	public  $mCommentPage	= NULL;		///< comment page id
	public  $mCommentTitle  = NULL;		///< comment page title
	public  $mCommentRev	= NULL;		///< comment revision id
	public  $mText			= NULL;		///< comment text

	public function __construct( WikilogItem &$item ) {
		$this->mItem = $item;
	}

	public function getID() {
		return $this->mID;
	}

	public function setUser( $user ) {
		$this->mUserID = $user->getId();
		$this->mUserText = $user->getName();
		$this->mAnonName = NULL;
	}

	public function setAnon( $name ) {
		$this->mAnonName = $name;
	}

	public function getText() {
		return $this->mText;
	}

	public function setText( $text ) {
		$this->mText = $text;
		$this->mTextChanged = true;
	}

	public function isVisible() {
		return $this->mStatus == self::S_OK;
	}

	public function isTextChanged() {
		return $this->mTextChanged;
	}

	public function loadText() {
		$dbr = wfGetDB( DB_SLAVE );
		$rev = Revision::loadFromId( $dbr, $this->mCommentRev );
		$this->mText = $rev->getText();
		$this->mTextChanged = false;
	}

	public function saveComment() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();

		$data = array(
			'wlc_parent'    => $this->mParent,
			'wlc_post'      => $this->mItem->getID(),
			'wlc_user'      => $this->mUserID,
			'wlc_user_text' => $this->mUserText,
			'wlc_anon_name' => $this->mAnonName,
			'wlc_status'    => $this->mStatus,
			'wlc_timestamp' => $dbw->timestamp( $this->mTimestamp ),
			'wlc_updated'   => $dbw->timestamp( $this->mUpdated )
		);

		$delayed = array();

		# Main update.
		if ( $this->mID ) {
			$dbw->update( 'wikilog_comments', $data,
				array( 'wlc_id' => $this->mID ), __METHOD__ );
		} else {
			$cid = $dbw->nextSequenceValue( 'wikilog_comments_wlc_id' );
			$data = array( 'wlc_id' => $cid ) + $data;
			$dbw->insert( 'wikilog_comments', $data, __METHOD__ );
			$this->mID = $dbw->insertId();

			# Now that we have an ID, we can generate the thread.
			$this->mThread = self::getThreadHistory( $this->mID, $this->mParent );
			$delayed['wlc_thread'] = implode( '/', $this->mThread );
		}

		# Save article with comment text.
		if ( $this->mTextChanged ) {
			$this->mCommentTitle = $this->getCommentArticleTitle();
			$art = new Article( $this->mCommentTitle );
			$art->doEdit( $this->mText, $this->getAutoSummary() );
			$this->mTextChanged = false;

			$this->mCommentPage = $art->getID();
			$delayed['wlc_comment_page'] = $this->mCommentPage;
		}

		# Delayed updates.
		if ( !empty( $delayed ) ) {
			$dbw->update( 'wikilog_comments', $delayed,
				array( 'wlc_id' => $this->mID ), __METHOD__ );
		}

		# Update number of comments
		$this->mItem->updateNumComments( true );

		# Commit
		$dbw->commit();

		# Invalidate some caches.
		$this->mCommentTitle->invalidateCache();
		$this->mItem->mTitle->invalidateCache();
		$this->mItem->mTitle->getTalkPage()->invalidateCache();
		$this->mItem->mParentTitle->invalidateCache();
	}

	public function deleteComment() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();

		$dbw->delete( 'wikilog_comments', array( 'wlc_id' => $this->mID ), __METHOD__ );
		$this->mItem->updateNumComments( true );

		$dbw->commit();

		$this->mItem->mTitle->invalidateCache();
		$this->mItem->mTitle->getTalkPage()->invalidateCache();
		$this->mItem->mParentTitle->invalidateCache();
		$this->mID = NULL;
	}

	public function getCommentArticleTitle() {
		if ( $this->mCommentTitle ) {
			return $this->mCommentTitle;
		} else if ( $this->mCommentPage ) {
			return Title::newFromID( $this->mCommentPage, GAID_FOR_UPDATE );
		} else {
			$it = $this->mItem->mTitle;
			return Title::makeTitle(
				MWNamespace::getTalk( $it->getNamespace() ),
				$it->getText() . '/c' . self::padID( $this->mID )
			);
		}
	}

	public function getAutoSummary() {
		global $wgContLang;
		$user = $this->mUserID ? $this->mUserText : $this->mAnonName;
		$summ = $wgContLang->truncate( str_replace("\n", ' ', $this->mText),
			max( 0, 200 - strlen( wfMsgForContent( 'wikilog-comment-autosumm' ) ) ),
			'...' );
		return wfMsgForContent( 'wikilog-comment-autosumm', $user, $summ );
	}

	public static function getThreadHistory( $id, $parent ) {
		$thread = array();

		if ( $parent ) {
			$dbr = wfGetDB( DB_SLAVE );
			$thread = $dbr->selectField(
				'wikilog_comments',
				'wlc_thread',
				array( 'wlc_id' => intval( $parent ) ),
				__METHOD__
			);
			if ( $thread !== false ) {
				$thread = explode( '/', $thread );
			} else {
				throw MWException( 'Invalid parent history.' );
			}
		}

		$thread[] = self::padID( $id );
		return $thread;
	}

	public static function padID( $id ) {
		return str_pad( intval( $id ), 6, '0', STR_PAD_LEFT );
	}

	public static function newFromRow( &$item, $row ) {
		$comment = new WikilogComment( $item );
		$comment->mID           = intval( $row->wlc_id );
		$comment->mParent       = intval( $row->wlc_parent );
		$comment->mThread       = explode( '/', $row->wlc_thread );
		$comment->mUserID       = intval( $row->wlc_user );
		$comment->mUserText     = strval( $row->wlc_user_text );
		$comment->mAnonName     = strval( $row->wlc_anon_name );
		$comment->mStatus       = strval( $row->wlc_status );
		$comment->mTimestamp    = wfTimestamp( TS_MW, $row->wlc_timestamp );
		$comment->mUpdated      = wfTimestamp( TS_MW, $row->wlc_updated );
		$comment->mCommentPage  = $row->wlc_comment_page;

		# This information may not be available for deleted comments.
		if ( $row->page_title && $row->page_latest ) {
			$comment->mCommentTitle = Title::makeTitle( $row->page_namespace, $row->page_title );
			$comment->mCommentRev = $row->page_latest;
		}
		return $comment;
	}

	public static function newFromText( &$item, $text, $parent = NULL ) {
		$ts = wfTimestamp( TS_MW );
		$comment = new WikilogComment( $item );
		$comment->mParent    = $parent;
		$comment->mStatus    = self::S_OK;
		$comment->mTimestamp = $ts;
		$comment->mUpdated   = $ts;
		$comment->setText( $text );
		return $comment;
	}

	public static function newFromID( &$item, $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		$row = self::loadFromID( $dbr, $id );
		if ( $row ) {
			return self::newFromRow( $item, $row );
		}
		return NULL;
	}

	public static function newFromPageID( &$item, $pageid ) {
		$dbr = wfGetDB( DB_SLAVE );
		$row = self::loadFromPageID( $dbr, $pageid );
		if ( $row && $row->wlc_post == $item->getID() ) {
			return self::newFromRow( $item, $row );
		}
		return NULL;
	}

	private static function loadFromConds( $dbr, $conds ) {
		extract( self::selectInfo( $dbr ) );	// $tables, $fields
		$row = $dbr->selectRow(
			$tables,
			$fields,
			$conds,
			__METHOD__,
			array( )
		);
		return $row;
	}

	private static function loadFromID( $dbr, $id ) {
		return self::loadFromConds( $dbr, array( 'wlc_id' => $id ) );
	}

	private static function loadFromPageID( $dbr, $pageid ) {
		return self::loadFromConds( $dbr, array( 'wlc_comment_page' => $pageid ) );
	}

	public static function fetchAllFromItem( $dbr, $itemid ) {
		return self::fetchFromConds( $dbr,
			array( 'wlc_post' => $itemid ),
			array( 'ORDER BY' => 'wlc_thread, wlc_id' )
		);
	}

	public static function fetchAllFromItemThread( $dbr, $itemid, $thread ) {
		if ( is_array( $thread ) ) {
			$thread = implode( '/', $thread );
		}
		$thread = $dbr->escapeLike( $thread );
		return self::fetchFromConds( $dbr,
			array( 'wlc_post' => $itemid, "wlc_thread LIKE '{$thread}/%'" ),
			array( 'ORDER BY' => 'wlc_thread, wlc_id' )
		);
	}

	private static function fetchFromConds( $dbr, $conds, $options = array() ) {
		extract( self::selectInfo( $dbr ) );	// $tables, $fields
		$result = $dbr->select(
			$tables,
			$fields,
			$conds,
			__METHOD__,
			$options
		);
		return $result;
	}

	private static function selectInfo( $dbr ) {
		extract( $dbr->tableNames( 'wikilog_comments', 'page' ) );
		return array(
			'tables' =>
				"{$wikilog_comments} ".
				"LEFT JOIN {$page} ON (page_id = wlc_comment_page)",
			'fields' => array(
				'wlc_id',
				'wlc_parent',
				'wlc_thread',
				'wlc_post',
				'wlc_user',
				'wlc_user_text',
				'wlc_anon_name',
				'wlc_status',
				'wlc_timestamp',
				'wlc_updated',
				'wlc_comment_page',
				'page_namespace',
				'page_title',
				'page_latest'
			)
		);
	}

}
