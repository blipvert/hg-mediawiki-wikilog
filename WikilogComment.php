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


class WikilogComment {
	const S_OK				= 'OK';
	const S_PENDING			= 'PENDING';
	const S_HIDDEN_SYSOP	= 'HIDDEN_SYSOP';
	const S_DELETED_USER	= 'DELETED_USER';
	const S_DELETED_SYSOP	= 'DELETED_SYSOP';

	public static $statusMap = array(
		self::S_OK				=> false,
		self::S_PENDING			=> 'pending',
		self::S_HIDDEN_SYSOP	=> 'hidden',
		self::S_DELETED_USER	=> 'deleted',
		self::S_DELETED_SYSOP	=> 'deleted'
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

		# Update number of comments.
		$this->mItem->updateNumComments( true );

		$dbw->commit();
	}

	public function getCommentArticleTitle() {
		if ( $this->mCommentTitle ) {
			return $this->mCommentTitle;
		} else if ( $this->mCommentPage ) {
			return Title::newFromID( $this->mCommentPage, GAID_FOR_UPDATE );
		} else {
			$it = $this->mItem->mItemTitle;
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
		return self::newFromData( $item, get_object_vars( $row ) );
	}

	public static function newFromData( &$item, $data ) {
		$comment = new WikilogComment( $item );
		$comment->mID           = intval( $data['wlc_id'] );
		$comment->mParent       = $data['wlc_parent'];
		$comment->mThread       = explode( '/', $data['wlc_thread'] );
		$comment->mUserID       = $data['wlc_user'];
		$comment->mUserText     = $data['wlc_user_text'];
		$comment->mAnonName     = $data['wlc_anon_name'];
		$comment->mStatus       = $data['wlc_status'];
		$comment->mTimestamp    = wfTimestamp( TS_MW, $data['wlc_timestamp'] );
		$comment->mUpdated      = wfTimestamp( TS_MW, $data['wlc_updated'] );
		$comment->mCommentPage  = $data['wlc_comment_page'];

		# This information may not be available for deleted comments.
		if ( $data['page_title'] && $data['page_latest'] ) {
			$comment->mCommentTitle =
				Title::makeTitle( $data['page_namespace'], $data['page_title'] );
			$comment->mCommentRev = $data['page_latest'];
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
		$row = self::commentDataFromID( $dbr, $id );
		if ( $row ) {
			return self::newFromRow( $item, $row );
		} else {
			return NULL;
		}
	}

	public static function newFromPageID( &$item, $pageid ) {
		$dbr = wfGetDB( DB_SLAVE );
		$row = self::commentDataFromPageID( $dbr, $pageid );
		if ( $row && $row->wlc_post == $item->getID() ) {
			return self::newFromRow( $item, $row );
		} else {
			return NULL;
		}
	}

	private static function commentDataFromConditions( $dbr, $conditions ) {
		$row = $dbr->selectRow(
			array(
				'wikilog_comments',
				'page'
			),
			array(
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
			),
			$conditions,
			__METHOD__,
			array( ),
			array(
				'page' => array( 'LEFT JOIN', 'wlc_comment_page = page_id' )
			)
		);
		return $row;
	}

	private static function commentDataFromID( $dbr, $id ) {
		return self::commentDataFromConditions( $dbr, array( 'wlc_id' => $id ) );
	}

	private static function commentDataFromPageID( $dbr, $pageid ) {
		return self::commentDataFromConditions( $dbr, array( 'wlc_comment_page' => $pageid ) );
	}

}
