/**
 * Logs tab filtering and copy-to-clipboard support for the BookFunnel admin page.
 *
 * @package BookFunnelWooCommerce
 */
( function () {
	'use strict';

	var toolbar = document.getElementById( 'bf-wc-log-toolbar' );

	if ( ! toolbar ) {
		return;
	}

	var filterSelect = document.getElementById( 'bf-wc-log-filter' );
	var copyButton   = document.getElementById( 'bf-wc-log-copy' );
	var table        = document.getElementById( 'bf-wc-log-table' );
	var emptyNotice  = document.getElementById( 'bf-wc-log-empty-filter' );
	var rows         = table ? Array.prototype.slice.call( table.querySelectorAll( 'tbody tr' ) ) : [];

	/**
	 * Determine whether a row matches the current filter selection.
	 *
	 * @param {Element} row    Table row.
	 * @param {string}  filter Selected filter value.
	 * @param {number}  index  Row index in DOM order (newest first).
	 * @return {boolean}
	 */
	function rowMatchesFilter( row, filter, index ) {
		if ( 'errors' === filter ) {
			return 'error' === row.dataset.level;
		}

		if ( '50' === filter ) {
			return index < 50;
		}

		if ( '100' === filter ) {
			return index < 100;
		}

		var days   = '30d' === filter ? 30 : 7;
		var cutoff = Date.now() - ( days * 24 * 60 * 60 * 1000 );
		var rowTime = Date.parse( row.dataset.timestamp );

		return isNaN( rowTime ) || rowTime >= cutoff;
	}

	/**
	 * Apply the current filter selection to the log table.
	 *
	 * @return {void}
	 */
	function applyFilter() {
		var filter       = filterSelect.value;
		var visibleCount = 0;

		rows.forEach( function ( row, index ) {
			if ( rowMatchesFilter( row, filter, index ) ) {
				row.classList.remove( 'bf-wc-log-hidden' );
				visibleCount++;
			} else {
				row.classList.add( 'bf-wc-log-hidden' );
			}
		} );

		if ( table ) {
			table.style.display = visibleCount > 0 ? '' : 'none';
		}

		if ( emptyNotice ) {
			emptyNotice.style.display = visibleCount > 0 ? 'none' : '';
		}
	}

	/**
	 * Get the rows currently visible under the active filter.
	 *
	 * @return {Array<Element>}
	 */
	function getVisibleRows() {
		return rows.filter( function ( row ) {
			return ! row.classList.contains( 'bf-wc-log-hidden' );
		} );
	}

	/**
	 * Build the plain-text export for the currently filtered log rows.
	 *
	 * @return {string}
	 */
	function buildExportText() {
		var filterLabel = filterSelect.options[ filterSelect.selectedIndex ].text;
		var header       = [
			'BookFunnel WooCommerce Plugin — Support Log Export',
			'Site: ' + ( toolbar.dataset.siteUrl || '' ),
			'Exported: ' + new Date().toString(),
			'Filter: ' + filterLabel,
			'---',
		];

		var entries = getVisibleRows().map( function ( row ) {
			var timestamp = row.dataset.timestamp || '';
			var level     = ( row.dataset.level || '' ).toUpperCase();
			var message   = ( row.children[ 2 ] ? row.children[ 2 ].textContent : '' ).trim();
			var contextEl = row.querySelector( '.bf-wc-log-context' );
			var line       = '[' + timestamp + '] ' + level + ': ' + message;

			if ( contextEl && contextEl.textContent.trim() ) {
				line += '\nContext: ' + contextEl.textContent.trim();
			}

			return line;
		} );

		return header.join( '\n' ) + '\n\n' + entries.join( '\n\n' );
	}

	/**
	 * Copy text to the clipboard, falling back to a hidden textarea when the
	 * Clipboard API isn't available.
	 *
	 * @param {string} text Text to copy.
	 * @return {void}
	 */
	function copyToClipboard( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( showCopiedFeedback, function () {
				fallbackCopy( text );
			} );
			return;
		}

		fallbackCopy( text );
	}

	/**
	 * Fallback clipboard copy for browsers without the Clipboard API.
	 *
	 * @param {string} text Text to copy.
	 * @return {void}
	 */
	function fallbackCopy( text ) {
		var textarea = document.createElement( 'textarea' );
		textarea.value = text;
		textarea.style.position = 'fixed';
		textarea.style.opacity = '0';
		document.body.appendChild( textarea );
		textarea.focus();
		textarea.select();

		try {
			document.execCommand( 'copy' );
			showCopiedFeedback();
		} catch ( err ) {
			window.alert( 'Unable to copy logs automatically. Please select and copy the log table manually.' );
		}

		document.body.removeChild( textarea );
	}

	/**
	 * Briefly change the Copy button label to confirm success.
	 *
	 * @return {void}
	 */
	function showCopiedFeedback() {
		var originalText = copyButton.textContent;
		copyButton.textContent = 'Copied!';

		setTimeout( function () {
			copyButton.textContent = originalText;
		}, 1500 );
	}

	if ( filterSelect ) {
		filterSelect.addEventListener( 'change', applyFilter );
		applyFilter();
	}

	if ( copyButton ) {
		copyButton.addEventListener( 'click', function () {
			copyToClipboard( buildExportText() );
		} );
	}
} )();
