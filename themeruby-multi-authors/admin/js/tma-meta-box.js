/**
 * ThemeRuby Multi Authors - Meta Box Support
 *
 * Provides tag-like interface for selecting multiple authors.
 * Works in both Classic Editor and Block Editor.
 *
 * @package ThemeRuby_Multi_Authors
 * @since 1.0.0
 */

(function($) {
	'use strict';

	var searchTimeout = null;
	var $searchInput = null;
	var $suggestions = null;
	var $selectedAuthors = null;
	var primaryAuthorId = 0;
	var selectedIndex = -1;

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		$searchInput = $('#tmauthors_search_input');
		$suggestions = $('#tmauthors-suggestions');
		$selectedAuthors = $('#tmauthors-selected-authors');
		primaryAuthorId = parseInt($('#tmauthors-primary-author').val(), 10);

		if (!$searchInput.length) {
			return;
		}

		// Initialize event handlers.
		initSearchInput();
		initRemoveButtons();
		initSuggestionClicks();
		initClickOutside();
	});

	/**
	 * Initialize search input events.
	 */
	function initSearchInput() {
		$searchInput.on('keyup', function(e) {
			// Skip for arrow keys and Enter.
			if (['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].indexOf(e.key) !== -1) {
				return;
			}

			var searchTerm = $(this).val().trim();

			// Clear previous timeout.
			if (searchTimeout) {
				clearTimeout(searchTimeout);
			}

			// Hide suggestions if empty or too short.
			if (searchTerm.length < 1) {
				$suggestions.removeClass('active');
				return;
			}

			// Delay search to avoid too many AJAX calls.
			searchTimeout = setTimeout(function() {
				searchAuthors(searchTerm);
			}, 300);
		});

		// Clear input on focus if it matches placeholder.
		$searchInput.on('focus', function() {
			$(this).removeClass('form-input-tip');
		});

		// Restore placeholder styling if empty on blur.
		$searchInput.on('blur', function() {
			if ($(this).val() === '') {
				$(this).addClass('form-input-tip');
			}
		});

		// Handle keyboard navigation.
		$searchInput.on('keydown', function(e) {
			if (!$suggestions.is(':visible')) {
				return;
			}

			var $items = $suggestions.find('.tmauthors-suggestion-item');
			var itemCount = $items.length;

			if (e.key === 'ArrowDown') {
				e.preventDefault();
				selectedIndex = (selectedIndex + 1) % itemCount;
				updateSelectedSuggestion($items, selectedIndex);
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				selectedIndex = selectedIndex <= 0 ? itemCount - 1 : selectedIndex - 1;
				updateSelectedSuggestion($items, selectedIndex);
			} else if (e.key === 'Enter') {
				e.preventDefault();
				if (selectedIndex >= 0 && selectedIndex < itemCount) {
					$items.eq(selectedIndex).trigger('click');
				} else if (itemCount > 0) {
					$items.first().trigger('click');
				}
				selectedIndex = -1;
			} else if (e.key === 'Escape') {
				$suggestions.removeClass('active');
				selectedIndex = -1;
			}
		});
	}

	/**
	 * Update selected suggestion highlighting.
	 */
	function updateSelectedSuggestion($items, index) {
		$items.removeClass('selected');
		if (index >= 0 && index < $items.length) {
			$items.eq(index).addClass('selected');
		}
	}

	/**
	 * Initialize remove button events.
	 */
	function initRemoveButtons() {
		$selectedAuthors.on('click', '.tmauthors-remove-author', function(e) {
			e.preventDefault();
			$(this).closest('.tmauthors-author-tag').fadeOut(200, function() {
				$(this).remove();
			});
		});
	}

	/**
	 * Initialize suggestion click events.
	 */
	function initSuggestionClicks() {
		$suggestions.on('click', '.tmauthors-suggestion-item', function(e) {
			e.preventDefault();
			var authorId = $(this).data('author-id');
			var authorName = $(this).data('author-name');
			addAuthor(authorId, authorName);
			$searchInput.val('').addClass('form-input-tip');
			$suggestions.removeClass('active');
			selectedIndex = -1;
		});

		// Highlight on hover.
		$suggestions.on('mouseenter', '.tmauthors-suggestion-item', function() {
			var $items = $suggestions.find('.tmauthors-suggestion-item');
			$items.removeClass('selected');
			$(this).addClass('selected');
			selectedIndex = $items.index(this);
		});
	}

	/**
	 * Initialize click outside to close suggestions.
	 */
	function initClickOutside() {
		$(document).on('click', function(e) {
			if (!$(e.target).closest('.tmauthors-search-wrapper').length &&
				!$(e.target).closest('.tmauthors-suggestions').length) {
				$suggestions.removeClass('active');
				selectedIndex = -1;
			}
		});
	}

	/**
	 * Search authors via AJAX.
	 */
	function searchAuthors(searchTerm) {
		// Get already selected author IDs to exclude from search.
		var selectedIds = getSelectedAuthorIds();

		// Show loading state.
		$suggestions.html('<div class="tmauthors-loading">' +
			'<span class="spinner is-active"></span> Searching...' +
			'</div>').addClass('active');

		$.ajax({
			url: tmAuthorsAdmin.ajaxUrl,
			type: 'GET',
			data: {
				action: 'tmauthors_search_authors',
				search: searchTerm,
				nonce: tmAuthorsAdmin.nonce,
				exclude: selectedIds.join(',')
			},
			success: function(response) {
				if (response.success && response.data.length > 0) {
					displaySuggestions(response.data);
				} else {
					$suggestions.html('<div class="tmauthors-no-results">' +
						tmAuthorsAdmin.i18n.noResults +
						'</div>').addClass('active');
				}
			},
			error: function() {
				$suggestions.removeClass('active');
			}
		});
	}

	/**
	 * Display author suggestions.
	 */
	function displaySuggestions(authors) {
		var html = '';

		for (var i = 0; i < authors.length; i++) {
			html += '<div class="tmauthors-suggestion-item" ' +
				'data-author-id="' + authors[i].id + '" ' +
				'data-author-name="' + escapeHtml(authors[i].name) + '">';
			html += '<strong>' + escapeHtml(authors[i].name) + '</strong>';
			if (authors[i].text !== authors[i].name) {
				html += '<small>' + escapeHtml(authors[i].text) + '</small>';
			}
			html += '</div>';
		}

		$suggestions.html(html).addClass('active');
		selectedIndex = -1;
	}

	/**
	 * Add author to selected list.
	 */
	function addAuthor(authorId, authorName) {
		// Check if already added.
		if ($selectedAuthors.find('[data-author-id="' + authorId + '"]').length > 0) {
			return;
		}

		// Build tag HTML (WordPress tag style).
		var tagId = 'tmauthors-remove-' + authorId;
		var html = '<span class="tmauthors-author-tag" data-author-id="' + authorId + '">';
		html += '<button type="button" id="' + tagId + '" class="ntdelbutton tmauthors-remove-author">';
		html += '<span class="remove-tag-icon" aria-hidden="true"></span>';
		html += '<span class="screen-reader-text">' + tmAuthorsAdmin.i18n.remove + '</span>';
		html += '</button>';
		html += '&nbsp;' + escapeHtml(authorName);
		html += '<input type="hidden" name="tmauthors[]" value="' + authorId + '" />';
		html += '</span>';

		// Add with fade-in effect.
		var $tag = $(html).hide();
		$selectedAuthors.append($tag);
		$tag.fadeIn(200);
	}

	/**
	 * Get array of selected author IDs.
	 */
	function getSelectedAuthorIds() {
		var ids = [];
		$selectedAuthors.find('.tmauthors-author-tag').each(function() {
			var id = $(this).data('author-id');
			if (id) {
				ids.push(id);
			}
		});
		return ids;
	}

	/**
	 * Escape HTML to prevent XSS.
	 */
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) {
			return map[m];
		});
	}

})(jQuery);
