<?php
/**
 * This file implements the UI view for Tools > Email > Sent
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2014 by Francois Planque - {@link http://fplanque.com/}
 *
 * {@internal License choice
 * - If you have received this file as part of a package, please find the license.txt file in
 *   the same folder or the closest folder above for complete license terms.
 * - If you have received this file individually (e-g: from http://evocms.cvs.sourceforge.net/)
 *   then you must choose one of the following licenses before using the file:
 *   - GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *   - Mozilla Public License 1.1 (MPL) - http://www.opensource.org/licenses/mozilla1.1.php
 * }}
 *
 * {@internal Open Source relicensing agreement:
 * }}
 *
 * @package admin
 *
 * @version $Id: _email_sent_details.view.php 7616 2014-11-12 14:50:13Z yura $
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

global $MailLog;

$Form = new Form( NULL, 'mail_log', 'post', 'compact' );

$Form->global_icon( T_('Cancel viewing!'), 'close', regenerate_url( 'blog' ) );

$Form->begin_form( 'fform', sprintf( T_('Mail log ID#%s'), $MailLog->emlog_ID ) );

$Form->info( T_('Result'), emlog_result_info( $MailLog->emlog_result ) );

$Form->info( T_('Date'), mysql2localedatetime_spans( $MailLog->emlog_timestamp, 'Y-m-d', 'H:i:sP' ) );

$deleted_user_note = '';
if( $MailLog->emlog_user_ID > 0 )
{
	$UserCache = & get_UserCache();
	if( $User = $UserCache->get_by_ID( $MailLog->emlog_user_ID, false ) )
	{
		$Form->info( T_('To User'), $User->get_identity_link() );
	}
	else
	{
		$deleted_user_note = '( '.T_( 'Deleted user' ).' )';
	}
}

$Form->info( T_('To'), '<pre class="email_log"><span>'.htmlspecialchars($MailLog->emlog_to).$deleted_user_note.'</span></pre>' );

$Form->info( T_('Subject'), '<pre class="email_log"><span>'.htmlspecialchars($MailLog->emlog_subject).'</span></pre>' );

$Form->info( T_('Headers'), '<pre class="email_log"><span>'.htmlspecialchars($MailLog->emlog_headers).'</span></pre>' );

$mail_contents = mail_log_parse_message( $MailLog->emlog_headers, $MailLog->emlog_message );

if( !empty( $mail_contents ) )
{
	if( !empty( $mail_contents['text'] ) )
	{ // Display Plain Text content
		$Form->info( T_('Text content'), $mail_contents['text']['type']
				.'<pre class="email_log_scroll"><span>'.htmlspecialchars( $mail_contents['text']['content'] ).'</span></pre>' );
	}
	if( !empty( $mail_contents['html'] ) )
	{ // Display HTML content
		if( ! empty( $mail_contents['html']['head_style'] ) )
		{ // Print out all styles of email message
			echo '<style>'.$mail_contents['html']['head_style'].'</style>';
		}
		$div_html_class = empty( $mail_contents['html']['body_class'] ) ? '' : ' '.$mail_contents['html']['body_class'];
		$div_html_style = empty( $mail_contents['html']['body_style'] ) ? '' : ' style="'.$mail_contents['html']['body_style'].'"';
		$Form->info( T_('HTML content'), $mail_contents['html']['type']
				.'<div class="email_log_html'.$div_html_class.'"'.$div_html_style.'>'.$mail_contents['html']['content'].'</div>' );
	}
}

$Form->info( T_('Raw email source'), '<pre class="email_log_scroll"><span>'.htmlspecialchars($MailLog->emlog_message).'</span></pre>' );

$Form->end_form();

?>