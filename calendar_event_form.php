<!-- ADD / EDIT EVENT MODAL -->
<div class="modal-overlay <?php echo ($error !== "") ? 'active' : ''; ?>" id="addEventModal">
  <div class="modal-container">
    <div class="modal-header">
      <h2 id="modalTitle">
        <?php echo $formAction === "update_event" ? "Edit Event" : "Add Event"; ?>
      </h2>
    </div>
    <div class="modal-body">
      <?php if ($error !== ""): ?>
        <p role="alert" style="color:#d93025;margin-bottom:14px;">
          <?php echo calendarEscape($error); ?>
        </p>
      <?php endif; ?>
      <form action="calendar.php?month=<?php echo $month; ?>&year=<?php echo $year; ?><?php echo $showAll ? '&view=all' : ''; ?>" method="POST" id="eventForm">
        <?php if (!empty($dashboardEventForm)): ?><input type="hidden" name="return_dashboard" value="1"><?php endif; ?>
        <input type="hidden" name="csrf" value="<?= calendarEscape($_SESSION['calendar_csrf']) ?>">
        <input type="hidden" name="form_action" id="formAction" value="<?php echo calendarEscape($formAction); ?>">
        <input type="hidden" name="event_id" id="event_id" value="<?php echo calendarEscape($editingEventId); ?>">
        <div class="modal-form-grid">
          <div class="form-field">
            <label for="event_name">Event Name</label>
            <input type="text" id="event_name" name="event_name" placeholder="Enter event name" value="<?php echo calendarEscape($formValues["event_name"]); ?>" required>
          </div>
          <div class="form-field">
            <label for="start_time">Start Time</label>
            <input type="time" id="start_time" name="start_time" value="<?php echo calendarEscape($formValues["start_time"]); ?>" required>
          </div>
          <div class="form-field">
            <label for="event_date">Date</label>
            <input type="date" id="event_date" name="event_date" value="<?php echo calendarEscape($formValues["event_date"]); ?>" required>
          </div>
          <div class="form-field">
            <label for="end_time">End Time</label>
            <input type="time" id="end_time" name="end_time" value="<?php echo calendarEscape($formValues["end_time"]); ?>">
          </div>
          <div class="form-field">
            <label for="event_type">Event Type</label>
            <select id="event_type" name="event_type" required>
              <option value="catering" <?php echo $formValues["event_type"] === "catering" ? "selected" : ""; ?>>Catering Event</option>
              <option value="inventory" <?php echo $formValues["event_type"] === "inventory" ? "selected" : ""; ?>>Inventory Restock</option>
              <option value="meeting" <?php echo $formValues["event_type"] === "meeting" ? "selected" : ""; ?>>Meeting</option>
              <option value="other" <?php echo $formValues["event_type"] === "other" ? "selected" : ""; ?>>Other</option>
            </select>
          </div>
          <div class="form-field">
            <label for="location">Location</label>
            <input type="text" id="location" name="location" placeholder="Enter Location" value="<?php echo calendarEscape($formValues["location"]); ?>">
          </div>
          <div class="form-field">
            <label for="description">Event Description</label>
            <textarea id="description" name="description" placeholder="Event Description (optional)"><?php echo calendarEscape($formValues["description"]); ?></textarea>
          </div>
          <div class="form-field">
            <label for="assigned_staff">Assigned Staff</label>
            <input type="text" id="assigned_staff" name="assigned_staff" placeholder="Enter staff name" value="<?php echo calendarEscape($formValues["assigned_staff"]); ?>" style="margin-bottom:12px;">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" placeholder="Add notes (optional)"><?php echo calendarEscape($formValues["notes"]); ?></textarea>
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-modal-cancel" id="closeModalBtn">Cancel</button>
          <button type="submit" class="btn-modal-save" id="saveModalBtn">
            <?php echo $formAction === "update_event" ? "Update Event" : "Save Event"; ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
