<?php
/**
 * Plugin Name: SENYLRC SEAL Search
 * Description: Adds SEAL logo on the left, search form in the center (hidden on homepage), and login/logout button on the right.
 * Version: 1.7
 * Author: SENYLRC
 */

add_action('get_header', 'senylrc_seal_search_block');

function senylrc_seal_search_block() {
    if (is_admin()) return; // don't show inside wp-admin
    ?>
    <div class="senylrc-seal-search-header">
      
      <!-- Left: Logo -->
      <div class="seal-search-left">
        <img 
          src="https://sealbeta.senylrc.org/wp-content/uploads/2025/01/cropped-SEAL_header_logo_4.gif" 
          alt="SEAL Logo" 
          style="max-height:150px; width:125px;">
      </div>

      <!-- Center: Search form (hidden on homepage) -->
      <div class="seal-search-center">
        <?php if ( !is_front_page() && !is_home() ) : ?>
          <h3 class="seal-search-title">Search the Catalog</h3>
          <form onsubmit="formSubmit();return false;" onchange="saveUserSelections();" id="search" name="search">
            <label>
              <input type="text" size="50%" id="query" name="query">
            </label>
            <br/>
            Select category: 
            <select name="category_filter" title="Select library-type category" id="category_filter">
              <option value="all">All</option>
              <option selected="selected" value="category~senylrc_participant">All Lenders</option>
              <option value="category~senylrc_academics">Academic Library Lenders</option>
              <option value="category~senylrc_public">Public Library Lenders</option>
              <option value="category~senylrc_school">School Library Lenders</option>
              <option value="category~senylrc_special">Special Library Lenders</option>
              <option value="category~senylrc_state">State Library</option>
            </select> 
            <input type="submit" value="SEARCH" id="submit" name="submit" class="seal-btn">
            <br>&nbsp;&nbsp; or &nbsp;&nbsp; 
            <a href="https://senylrc.indexdata.com/advanced.html">Advanced Search</a>&nbsp;&nbsp; - &nbsp;&nbsp; 
            <a target="_blank" href="https://libguides.senylrc.org/seal">Need help?</a>

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
          <a href="/wp-login.php" class="seal-btn">Staff Login</a>
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
        max-width: 1200px;  /* keeps it inside theme container */
        margin: 0 auto;
      }
      .seal-search-left {
        flex: 0 0 auto;
      }
      .seal-search-center {
        flex: 1;
        text-align: center;
        line-height: 1.2; /* tighter spacing */
      }
      .seal-search-title {
        margin: 0 0 8px 0;
        font-size: 1.25rem;
      }
      .seal-search-right {
        flex: 0 0 auto;
        text-align: right;
      }
      .seal-search-left img {
        max-height: 80px;
        width: auto;
      }
      .seal-btn,
      .seal-search-center input[type="submit"] {
        display: inline-block;
        background: #005ea2; /* main blue */
        color: #fff;
        padding: 6px 14px;
        text-decoration: none;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s ease;
      }
      .seal-btn:hover,
      .seal-search-center input[type="submit"]:hover {
        background: #004578; /* darker blue on hover */
      }
    </style>
    <?php
}
