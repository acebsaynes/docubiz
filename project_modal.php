<dialog id="dbProjectModal" class="db-modal" aria-labelledby="dbTitle">
  <div class="db-header"><h2 id="dbTitle">Add Project</h2><button type="button" data-close aria-label="Close">×</button></div>
  <form id="dbForm">
    <div id="dbBody"></div>
    <p id="dbError" role="alert" hidden></p>
    <div class="db-actions"><button type="button" id="dbBack" class="db-outline">Cancel</button><button type="submit" id="dbNext" class="db-next">Next</button></div>
  </form>
</dialog>
<dialog id="dbTerms" class="db-modal" aria-labelledby="dbTermsTitle">
  <div class="db-header"><h2 id="dbTermsTitle">Terms and Conditions</h2><button type="button" id="dbTermsClose" aria-label="Close">×</button></div>
  <div class="db-terms">
    <h3>Payment Terms</h3>
    <p>To confirm the reservation:</p>
    <p><span>Reservation Fee:</span> ₱5,000 (To secure the event date. This amount will be deducted from the total package price.)<br>
    <span>50% Down Payment:</span> For bookings made less than one (1) month before the event.<br>
    <span>Balance Payment:</span> To be paid on the day of the event, including any approved last-minute additions.<br>
    <span>100% Advance Payment:</span> Required for bookings made within seven (7) days up to two (2) days before the event.
    <h3>Payment Methods:</h3>
    <p><b>Bank:</b> BDO<br><b>Account Name:</b> LUCKYFIVE EVENTS CORPORATION<br><b>Account Number:</b> 004828010561</p>
    <h3>Terms and Conditions</h3>
    <ol><li>Reservation and Confirmation<ul><li>Reservations are confirmed upon receipt of the required reservation fee or applicable downpayment.</li><li>The remaining balance must be settled on or before the date of event.</li></ul></li></ol>
  </div>
  <div class="db-actions"><button type="button" id="dbDisagree" class="db-outline">Disagree</button><button type="button" id="dbAgree" class="db-save">Agree</button></div>
</dialog>
<script type="application/json" id="dbConfig"><?= json_encode($projectConfig, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script src="project_modal.js?v=<?= filemtime(__DIR__ . '/project_modal.js') ?>" defer></script>
