<?= $this->extend('marketing/layout') ?>
<?= $this->section('title') ?>Professional Print Tools. One Simple Subscription.<?= $this->endSection() ?>
<?= $this->section('head') ?>
<link rel="canonical" href="<?= site_url('/') ?>">
<meta property="og:title" content="Professional Print Tools. One Simple Subscription.">
<meta property="og:description" content="Meet your new print-production toolkit. Gang sheets, artwork preparation and custom QR codes — with a $9.99/month monthly plan.">
<meta property="og:image" content="<?= base_url('assets/img/printrivo_light_logo.png') ?>">
<?= $this->endSection() ?>
<?= $this->section('body_class') ?>sales-page<?= $this->endSection() ?>
<?= $this->section('main') ?>
<section class="hero container">
  <div class="hero-copy">
    <p class="eyebrow"><span class="status-dot" aria-hidden="true"></span> MADE FOR THE WORK BEFORE THE PRINT</p>
    <h1>Professional Print Tools.<br><span>One Simple Subscription.</span></h1>
    <p class="lead">Printrivo gives print shops the everyday production tools they need to prepare artwork, build gang sheets, and create QR codes—all in one place.</p>
    <p class="hero-audience">Spend less time fixing files and jumping between websites. Spend more time getting orders into production.</p>
    <div class="cta-row"><a class="button" href="<?= site_url('register') ?>">Start your 14-day free trial <span aria-hidden="true">→</span></a><a class="text-link" href="#demo">Watch the tools in action <span aria-hidden="true">↓</span></a></div>
    <p class="trial-reassurance"><strong>No charge during your 14-day trial.</strong> Payment setup required through PayPal. Then $9.99/month unless you cancel. Cancel before your trial ends to avoid a charge.</p>
    <p class="trial-detail">One trial per new customer. Cancel anytime; access continues through your trial or paid month.</p>
    <p class="alpha-offer"><strong>Early adopter?</strong> Enter <strong>Alpha</strong> at registration for a discounted monthly rate while continuously subscribed.</p>
  </div>
  <div class="suite-preview screenshot-showcase" id="demo" tabindex="-1" aria-label="Printrivo tool previews">
    <div class="preview-top"><span class="preview-dots" aria-hidden="true">● ● ●</span><span>YOUR PRINT-PRODUCTION WORKSPACE</span></div>
    <a class="hero-screenshot-link" href="<?= base_url('assets/img/Printrivo_GangsheetBuilder_Demo.gif') ?>" target="_blank" rel="noopener" aria-label="Enlarge Printrivo Gang Sheet Builder demo (opens in a new tab)"><img id="showcase-image" src="<?= base_url('assets/img/Printrivo_GangsheetBuilder_Demo.gif') ?>" width="900" height="506" alt="Animated demonstration of Printrivo Gang Sheet Builder" fetchpriority="high"></a>
    <div class="showcase-controls" aria-label="Choose a tool preview">
      <button type="button" class="showcase-choice" aria-pressed="true" data-preview="gang-sheet">Gang Sheet Builder</button>
      <button type="button" class="showcase-choice" aria-pressed="false" data-preview="image-studio">Image Studio</button>
      <button type="button" class="showcase-choice" aria-pressed="false" data-preview="qr">QR Generator</button>
    </div>
    <div class="preview-caption">Your tools, in action. Select a preview or click the image to enlarge.</div>
  </div>
</section>
<div class="audience-strip"><div class="container"><span>BUILT AROUND YOUR WORKFLOW</span><strong>DTF &amp; apparel</strong><strong>Print &amp; sign shops</strong><strong>Designers &amp; creators</strong></div></div>
<section class="section container production-story">
  <div class="section-heading"><div><p class="eyebrow">BEFORE THE FIRST PRINT</p><h2>The order is ready.<br>The artwork usually isn’t.</h2></div><p>A tiny JPG pulled from social media. A PNG with a white background. A logo that looks fine on a phone but falls short at print size. The work starts before anything reaches the printer.</p></div>
  <div class="production-pain-grid"><article><span>01 / CHECK</span><h3>Catch the file problems.</h3><p>Review resolution and transparency before committing media, ink, garments, and production time.</p></article><article><span>02 / PREPARE</span><h3>Keep the prep moving.</h3><p>Clean up simple backgrounds, size artwork, and arrange the designs you need for the next order.</p></article><article><span>03 / PRODUCE</span><h3>Leave with a useful file.</h3><p>Download your prepared artwork, gang sheet, or QR code. Review the result, then bring it into your print workflow.</p></article></div>
  <p class="production-tagline">Upload. Check. Prepare. Build. Download. Print.</p>
</section>

<section class="section container" id="tools">
  <div class="section-heading"><div><p class="eyebrow">YOUR EVERYDAY PRINT TOOLKIT</p><h2>Less jumping between apps.<br>More getting artwork ready.</h2></div><p>Give the everyday production tasks a place of their own. Three practical tools, designed around the files and layouts you work with.</p></div>
  <div class="feature-grid">
    <article class="feature-card"><div class="feature-number">01 <span>LAY OUT</span></div><a class="tool-screenshot" href="<?= base_url('assets/img/Printrivo_GangsheetBuilder_Demo.gif') ?>" target="_blank" rel="noopener" aria-label="Enlarge Printrivo Gang Sheet Builder demo (opens in a new tab)"><img src="<?= base_url('assets/img/Printrivo_GangsheetBuilder_Demo.gif') ?>" width="900" height="506" alt="Animated demonstration of Printrivo Gang Sheet Builder" loading="lazy" decoding="async"><span>View demo ↗</span></a><h3>Printrivo Gang Sheet Builder</h3><p>Turn individual designs into a production-ready gang sheet. Enter dimensions and quantities, arrange your artwork, and review coverage, remaining space, and resolution warnings before downloading.</p><ul><li>Build fitted 16- or 22-inch DTF or UVDTF sheets</li><li>Move, resize, rotate, and automatically arrange artwork</li><li>Check effective resolution before exporting</li><li>Track sheet usage and download 300 DPI PNGs</li></ul><a class="text-link" href="<?= site_url('register') ?>">Get ready to build <span aria-hidden="true">↗</span></a></article>
    <article class="feature-card"><div class="feature-number">02 <span>PREPARE</span></div><a class="tool-screenshot" href="<?= base_url('assets/img/Printrivo_Image_Studio_Demo.gif') ?>" target="_blank" rel="noopener" aria-label="Enlarge Printrivo Image Studio demo (opens in a new tab)"><img src="<?= base_url('assets/img/Printrivo_Image_Studio_Demo.gif') ?>" width="900" height="506" alt="Animated demonstration of Printrivo Image Studio" loading="lazy" decoding="async"><span>View demo ↗</span></a><h3>Printrivo Image Studio</h3><p>Know whether artwork is ready before you print it. Review customer files for resolution and transparency, clean up simple backgrounds, and prepare transparent images.</p><ul><li>Remove simple, edge-connected backgrounds</li><li>Crop artwork to its visible content</li><li>Upscale lower-DPI images when needed</li><li>Review print warnings and export 300 DPI PNGs</li></ul><a class="text-link" href="<?= site_url('register') ?>">Prepare your next design <span aria-hidden="true">↗</span></a></article>
    <article class="feature-card"><div class="feature-number">03 <span>CONNECT</span></div><a class="tool-screenshot" href="<?= base_url('assets/img/Printrivo_QR_Generator_Demo.gif') ?>" target="_blank" rel="noopener" aria-label="Enlarge Printrivo QR Generator demo (opens in a new tab)"><img src="<?= base_url('assets/img/Printrivo_QR_Generator_Demo.gif') ?>" width="900" height="506" alt="Animated demonstration of Printrivo QR Generator" loading="lazy" decoding="async"><span>View demo ↗</span></a><h3>Printrivo QR Generator</h3><p>Create QR codes for business cards, flyers, shirts, signs, menus, and packaging. Enter the destination, optionally add a logo, and download the code without leaving your production toolkit.</p><ul><li>Generate QR codes for your content or links</li><li>Add a built-in icon or your own logo</li><li>Preview the design before you download</li><li>Download PNG or SVG files for your layouts</li></ul><a class="text-link" href="<?= site_url('register') ?>">Create a connection <span aria-hidden="true">↗</span></a></article>
  </div>
</section>
<section class="workflow-section"><div class="container workflow-grid"><div><p class="eyebrow">FROM FILE TO FINISHED PREP</p><h2>A smoother start<br>to your next print job.</h2><p>Keep the practical part of print preparation in one suite, whether you’re setting up a customer order or building your own collection.</p></div><ol class="workflow"><li><span>1</span><div><h3>Bring your artwork</h3><p>Start with the designs, customer images, or links you already have.</p></div></li><li><span>2</span><div><h3>Make it production-ready</h3><p>Prepare the image, arrange the sheet, or customize your QR code.</p></div></li><li><span>3</span><div><h3>Export and keep moving</h3><p>Download your files, review the result, and move into your print workflow.</p></div></li></ol></div></section>
<section class="workflow-section"><div class="container workflow-grid"><div><p class="eyebrow">BUILT FROM EVERYDAY PRINT WORK</p><h2>The tools we wanted<br>in our own workflow.</h2><p>Printrivo started because we needed these tools ourselves. Real customer artwork means low-resolution logos, unwanted backgrounds, gang sheets to arrange, and QR codes to create.</p><p>We started building practical tools to handle those recurring jobs in one place. Printrivo is the result: print-production software built from actual print-production problems.</p></div><div class="production-note"><p class="eyebrow">SMALL TASKS. EVERY ORDER.</p><h3>A few minutes add up.</h3><p>Checking one file or arranging one sheet is a small job. Repeat it across orders and team members, and preparation becomes a meaningful part of the day.</p><p>Printrivo brings those tasks into one familiar workspace, helping you spend less time finding utilities and more time getting orders ready.</p><strong>One account. Three focused production tools.</strong></div></div></section>
<section class="section container"><div class="section-heading"><div><p class="eyebrow">BUILT FOR THE PEOPLE DOING THE PRINTING</p><h2>A place in your<br>daily production routine.</h2></div><p>Useful tools for the shops, decorators, and designers who turn customer ideas into printed products.</p></div><div class="audience-card-grid">
<article><h3>DTF print shops</h3><p>Prepare artwork and arrange 16″ or 22″ gang sheets with dimensions, quantities, and spacing under your control.</p></article>
<article><h3>Custom T-shirt shops</h3><p>Check incoming artwork for resolution and transparency before it reaches production.</p></article>
<article><h3>Apparel decorators</h3><p>Clean up customer images before the next preparation step. Keep artwork checks close at hand.</p></article>
<article><h3>Sign &amp; decal shops</h3><p>Prepare graphics and create QR codes for signs, vehicle decals, packaging, and promotional pieces.</p></article>
<article><h3>Graphic designers</h3><p>Check print dimensions, prepare transparent files, and export QR codes as PNG or SVG.</p></article>
<article><h3>Small print businesses</h3><p>Bring everyday production utilities into one account for $9.99/month.</p></article>
</div></section>
<section class="production-future"><div class="container"><p class="eyebrow">A TOOLKIT THAT KEEPS MOVING</p><h2>Three tools today.<br>More tools on the way.</h2><p>Start with Gang Sheet Builder, Image Studio, and QR Generator. More print-production tools are on the way to help you prepare designs and move orders into production.</p><p>Every new Printrivo tool is included while you stay subscribed, with no upgrade costs.</p></div></section>

<section class="section container" id="pricing">
  <?= $this->include('marketing/offer') ?>
</section>
<section class="section container faq-section" id="questions"><div><p class="eyebrow">A FEW GOOD QUESTIONS</p><h2>Before you get started.</h2></div><div class="faq-list">
  <details><summary>What is included in the subscription?</summary><p>The monthly offer includes the Printrivo Gang Sheet Builder, Printrivo Image Studio, and Printrivo QR Generator. All future Printrivo tools are included while subscribed, with no upgrade costs.</p></details>
  <details><summary>How do I subscribe?</summary><p>Create your account and continue to payment setup with PayPal. Eligible new members receive 14 days free once PayPal confirms the subscription, then pay $9.99/month automatically unless canceled. Cancel anytime from Subscription; access continues through your trial or paid month.</p></details>
  <details><summary>When will I be charged, and can I cancel?</summary><p>Payment setup is required through PayPal. New customers receive one 14-day free trial after their subscription is confirmed. Cancel before the trial ends to avoid a charge. Otherwise, your subscription renews automatically at $9.99/month, or your saved Alpha rate. You can cancel anytime from your subscription page; access continues through the trial or monthly period already paid for.</p></details>
  <details><summary>How does the Alpha discount work?</summary><p>The regular plan is $9.99/month. Enter Alpha in the optional discount-code field at registration to save the early-adopter promotion. Your code is saved for payment setup. The discounted price continues while your subscription stays continuously active. Registration does not start billing or unlock the tools.</p></details>
  <details><summary>What kind of artwork can Printrivo Image Studio improve?</summary><p>It works best on simple backgrounds and uses image resizing to prepare lower-DPI artwork. It is not AI background segmentation or detail reconstruction. Always inspect your exported artwork before printing.</p></details>
  <details><summary>Do I need to install desktop software?</summary><p>The tools run in your browser. For larger gang sheets and high-resolution artwork, a desktop or laptop gives you more room to work.</p></details>
</div></section>
<section class="closing-cta"><div class="container"><p class="eyebrow">YOUR NEXT PRINT JOB STARTS HERE</p><h2>Your production tools should keep production moving.</h2><p>Check artwork, prepare images, build gang sheets, and generate QR codes from one Printrivo account. $9.99/month.</p><a class="button light" href="<?= site_url('register') ?>">Start your 14-day free trial <span aria-hidden="true">→</span></a><p class="closing-trial-note">Payment setup required. No charge during your 14-day trial. Then $9.99/month unless canceled. One trial per new customer.</p></div></section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/sales.js?v=4-qr-generator-demo') ?>" defer></script>
<?= $this->endSection() ?>
