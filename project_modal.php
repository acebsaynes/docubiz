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
  <div class="db-terms" id="dbCateringTerms">
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
<div id="dbRentalTerms" class="db-rental-terms" hidden>
<section><h3>1. Reservation and Payment</h3><p>1.1. A 50% deposit is required upon booking to confirm the reservation.</p><p>1.2. The remaining balance must be paid on or before the date of delivery.</p><p>1.3. Deposits are non-refundable in case of cancellation.</p></section>
<section><h3>2. Rental Period</h3><p>2.1. The standard rental period is 24 hours, starting from the time of delivery until the agreed pickup schedule.</p><p>2.2. Any extension of rental time must be arranged in advance and may be subject to additional charges.</p></section>
<section><h3>3. Delivery and Setup</h3><p>3.1. Delivery and pickup fees may apply depending on the location.</p><p>3.2. The Lessee must ensure that the site is accessible, clear, and safe for setup.</p><p>3.3. Tents must not be moved once installed by the Lessor.</p><p>3.4. Arrangement of tables and chairs is not included in the basic rental package. If the Lessee requests setup/arrangement, an additional fee will be charged depending on the number of tables and chairs to be set up.</p></section>
<section><h3>4. Use of Rental Items</h3><p>4.1. Rental Items are to be used only for their intended purpose.</p><p>4.2. The Lessee is responsible for ensuring that tents, tables, and chairs are protected from damage due to negligence, misuse, or adverse conditions.</p><p>4.3. Open flames, cooking, or smoking are strictly prohibited inside or near the tents.</p></section>
<section><h3>5. Damage, Loss, and Liability</h3><p>5.1. The Lessee is responsible for any damage, loss, or theft of Rental Items during the rental period.</p><p>5.2. Charges for repair or replacement will be billed to the Lessee.</p><p>5.3. The Lessor shall not be held liable for accidents, injuries, or damages to persons or property arising from the use of the Rental Items.</p></section>
<section><h3>6. Weather Conditions</h3><p>6.1. Tents are designed for temporary shelter only and may not withstand severe weather conditions (e.g., strong winds, heavy rain during typhoon)</p><p>6.2. In case of unsafe weather, the Lessor reserves the right to cancel or remove the tents for safety reasons.</p></section>
<section><h3>7. Cancellation Policy</h3><p>7.1. Cancellations must be made at least 3 days prior to the event date.</p><p>7.2. Deposits are non-refundable, but payments made in excess of the deposit may be refunded if cancellation is within the allowed period.</p></section>
</div>
  <div class="db-actions"><button type="button" id="dbDisagree" class="db-outline">Disagree</button><button type="button" id="dbAgree" class="db-save">Agree</button></div>
</dialog>
<script type="application/json" id="dbConfig"><?= json_encode($projectConfig, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script src="project_modal.js?v=<?= filemtime(__DIR__ . '/project_modal.js') ?>" defer></script>
