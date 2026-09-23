<?php
/**
 * TEMPLATE ONLY — no real key in this file.
 *
 * Hostinger only shows an "Environment variables" panel for Node.js deployments, so on this
 * static/PHP deploy getenv('FMP_API_KEY') is always empty. Use a file instead.
 *
 * PREFERRED — outside the web root, so a git deploy cannot wipe it and the repo cannot leak it:
 *     hPanel → Files → File Manager
 *     go UP one level from public_html  (…/domains/harshwalia.com/)
 *     create  fmp-config.php  containing exactly the two lines below
 *
 * FALLBACK — copy this file to projects/config.local.php (git-ignored). Works, but a
 * redeploy may delete it, in which case the proxy starts returning HTTP 500 until restored.
 */
return 'your-financialmodelingprep-api-key-here';
