<?php
/**
 * Plugin Name: SENYLRC SEAL Search
 * Description: Adds SEAL logo on the left, search form in the center (hidden on homepage), and login/logout button on the right.
 * Version: 1.9
 * Author: SENYLRC
 */

add_action('get_header', 'senylrc_seal_search_block');

function senylrc_seal_search_block() {
    if (is_admin()) return; // don't show inside wp-admin
    ?>
    <div class="senylrc-seal-search-header" aria-label="SEAL header search and staff access">

      <!-- Left: Logo -->
      <div class="seal-search-left">
        <a href="<?php echo esc_url(home_url('/')); ?>" aria-label="Go to SEAL home">
          <img
            src="https://sealbeta.senylrc.org/wp-content/uploads/2025/01/cropped-SEAL_header_logo_4.gif"
            alt="SEAL logo"
            width="125"
            height="150"
            loading="eager"
            decoding="async">
        </a>
      </div>

      <!-- Center: Search form (hidden on homepage) -->
      <div class="seal-search-center">
        <?php if ( !is_front_page() && !is_home() ) : ?>
          <h2 class="seal-search-title" id="seal-search-title">Search the Catalog</h2>

          <form
            role="search"
            aria-labelledby="seal-search-title"
            onsubmit="formSubmit();return false;"
            onchange="saveUserSelections();"
            id="search"
            name="search"
          >
            <!-- Row 1: Query (keep the same visual spacing: no visible label) -->
            <div class="seal-query-row">
              <label for="query" class="sr-only">Search the catalog</label>
              <input
                type="text"
                id="query"
                name="query"
                class="seal-input"
                autocomplete="off"
                inputmode="search"
              >
            </div>

            <!-- Row 2: Category inline with select + button -->
            <div class="seal-controls-row" aria-label="Search filters">
              <label for="category_filter" class="seal-inline-label">Select category:</label>
              <select name="category_filter" id="category_filter" class="seal-select">
                <option value="all">All</option>
                <option selected="selected" value="category~senylrc_participant">All Lenders</option>
                <option value="category~senylrc_academics">Academic Library Lenders</option>
                <option value="category~senylrc_public">Public Library Lenders</option>
                <option value="category~senylrc_school">School Library Lenders</option>
                <option value="category~senylrc_special">Special Library Lenders</option>
                <option value="category~senylrc_state">State Library</option>
              </select>

              <input type="submit" value="SEARCH" id="submit" name="submit" class="seal-btn">
            </div>

            <!-- Row 3: Links line -->
            <div class="seal-links-row">
              <span class="seal-or" aria-hidden="true">or</span>
              <a class="seal-link" href="https://senylrc.indexdata.com/advanced.html">Advanced Search</a>
              <span class="seal-sep" aria-hidden="true">-</span>
              <a class="seal-link"
                 target="_blank"
                 rel="noopener noreferrer"
                 href="https://libguides.senylrc.org/seal">
                 Need help? <span class="sr-only">(opens in a new tab)</span>
              </a>
            </div>

            <!-- Your categories.js integration (kept as-is) -->
            <div id="categoriesSelect">
              <script src="https://senylrc.indexdata.com/mk2-ui-core/js/categories.js"></script>
              <script>
                var cats = new CategorySelectList("search", "category_filter", "categoriesComp", "categoryfilter");
                controlRegister.addControl(cats);
              </script>
              <span id="categoriesComp" class="componentTemplate"></span>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <!-- Right: Login/Logout Button -->
      <div class="seal-search-right">
        <?php if ( is_user_logged_in() ) : ?>
          <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="seal-btn">Staff Logout</a>
        <?php else : ?>
          <a href="<?php echo esc_url( home_url('/wp-login.php') ); ?>" class="seal-btn">Staff Login</a>
        <?php endif; ?>
      </div>

    </div>

    <style>
      .senylrc-seal-search-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        padding: 10px 0;
        max-width: 1200px;
        margin: 0 auto;
      }

      .seal-search-left { flex: 0 0 auto; }
      .seal-search-center { flex: 1; text-align: center; line-height: 1.25; }
      .seal-search-right { flex: 0 0 auto; text-align: right; }

      .seal-search-left img {
        max-height: 80px;
        width: auto;
      }

      .seal-search-title {
        margin: 0 0 8px 0;
        font-size: 1.25rem;
      }

      /* Row spacing to match your screenshot */
      .seal-query-row { margin: 0 0 8px 0; }
      .seal-controls-row { margin: 0 0 6px 0; }
      .seal-links-row { margin: 0; }

      /* Query box: 50% (with sane min/max so it matches your screenshot) */
      .seal-input {
        width: 50%;
        min-width: 340px;
        max-width: 640px;
        padding: 8px 10px;
        border: 1px solid #cfcfcf;
        border-radius: 4px;
        font-size: 16px;
      }

      /* Inline row: "Select category:" + dropdown + button */
      .seal-controls-row {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
      }

      .seal-inline-label {
        margin: 0;
        font-weight: 400; /* matches your screenshot look */
        white-space: nowrap;
      }

      .seal-select {
        width: 200px;
        max-width: 100%;
        padding: 8px 10px;
        border: 1px solid #cfcfcf;
        border-radius: 4px;
        font-size: 16px;
        min-height: 40px;
      }

      .seal-btn,
      .seal-search-center input[type="submit"] {
        display: inline-block;
        background: #005ea2;
        color: #fff;
        padding: 8px 14px;
        text-decoration: none;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.2s ease;
      }

      .seal-btn:hover,
      .seal-search-center input[type="submit"]:hover {
        background: #004578;
      }

      .seal-link { text-decoration: underline; }
      .seal-or { margin-right: 8px; }
      .seal-sep { margin: 0 8px; }

      /* Keyboard focus */
      .seal-btn:focus-visible,
      .seal-link:focus-visible,
      .seal-input:focus-visible,
      .seal-select:focus-visible {
        outline: 3px solid #ffbf47;
        outline-offset: 2px;
      }

      /* Screen-reader-only helper */
      .sr-only {
        position: absolute !important;
        width: 1px; height: 1px;
        padding: 0; margin: -1px;
        overflow: hidden; clip: rect(0, 0, 0, 0);
        white-space: nowrap; border: 0;
      }

      /* Mobile: stack neatly and avoid horizontal scrolling */
      @media (max-width: 768px) {
        .seal-input {
          width: 100%;
          min-width: 0;
          max-width: 100%;
        }
        .seal-controls-row {
          display: flex;
          flex-wrap: wrap;
          gap: 10px;
        }
        .seal-select {
          width: 100%;
        }
      }
    </style>
    <?php
}
