jQuery(document).ready(function($) {
    const app = {
        init: function() {
            this.cacheDOM();
            this.bindEvents();
            this.initCalendar();
            this.settings = antigravity_booking_vars.settings || { hourly_rate: 100, overnight_enabled: true };
            this.requestTimeout = 10000; // 10 second timeout
        },
        
        /**
         * Validate form inputs
         * @return {Array} Array of error messages
         */
        validateForm: function() {
            const errors = [];
            
            // Name validation
            const name = $('#customer_name').val().trim();
            if (name.length < 2) {
                errors.push('Name must be at least 2 characters');
            }
            
            // Email validation
            const email = $('#customer_email').val().trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                errors.push('Please enter a valid email address');
            }
            
            // Phone validation (optional but recommended)
            const phone = $('#customer_phone').val().trim();
            if (phone && !/^\+?[\d\s\-\(\)]{10,}$/.test(phone)) {
                errors.push('Please enter a valid phone number');
            }
            
            // Guest count validation
            const guests = parseInt($('#guest_count').val());
            if (guests < 1) {
                errors.push('Please enter number of guests');
            }
            
            return errors;
        },
        
        /**
         * Show loading state
         * @param {jQuery} $element - Element to show loading in
         * @param {string} message - Loading message
         */
        showLoading: function($element, message) {
            $element.html(`
                <div class="loading-container">
                    <div class="spinner"></div>
                    <p>${message}</p>
                </div>
            `);
        },
        
        /**
         * Show error message
         * @param {jQuery} $element - Element to show error in
         * @param {string} message - Error message
         */
        showError: function($element, message) {
            $element.html(`
                <div class="antigravity-error">
                    <p>${message}</p>
                    <button class="back-button" onclick="location.reload()">
                        Try Again
                    </button>
                </div>
            `);
        },
        
        /**
         * Show success message
         * @param {jQuery} $element - Element to show success in
         * @param {string} message - Success message
         */
        showSuccess: function($element, message) {
            $element.html(`
                <div class="antigravity-success">
                    <h3>✓ Booking Submitted!</h3>
                    <p>${message}</p>
                </div>
            `);
        },

        cacheDOM: function() {
            this.$app = $('#antigravity-booking-app');
            this.$calendarContainer = $('#antigravity-calendar-container');
            this.$timeSlotsContainer = $('#antigravity-time-slots');
            this.$bookingForm = $('#antigravity-booking-form');
            this.$form = $('#antigravity-booking-form-el');
            this.$slotsGrid = $('.slots-grid');
            this.$message = $('#antigravity-booking-message');
            
            // Outputs
            this.$inputDate = $('#selected_date');
            this.$inputStartTime = $('#selected_start_time');
            this.$inputEndTime = $('#selected_end_time');
            
            // Summary Fields
            this.$summaryDate = $('#summary-date');
            this.$summaryTime = $('#summary-time');
            this.$summaryCost = $('#summary-cost');
            this.$overnightNote = $('#overnight-note');
        },

        bindEvents: function() {
             this.$slotsGrid.on('click', '.time-slot', this.handleSlotClick.bind(this));
             this.$form.on('submit', this.handleFormSubmit.bind(this));
             this.$bookingForm.find('.back-button').on('click', this.showSlots.bind(this));
             
             // Keyboard navigation for time slots
             this.$slotsGrid.on('keydown', '.time-slot', (e) => {
                 if (e.key === 'Enter' || e.key === ' ') {
                     e.preventDefault();
                     $(e.currentTarget).click();
                 }
             });
             
             // Escape key to go back
             $(document).on('keydown', (e) => {
                 if (e.key === 'Escape') {
                     if (!this.$calendarContainer.is(':visible')) {
                         this.showCalendar();
                     }
                 }
             });
             
             // Back button keyboard support
             $('#back-to-cal-2').on('keydown', (e) => {
                 if (e.key === 'Enter' || e.key === ' ') {
                     e.preventDefault();
                     this.showCalendar();
                 }
             });
        },

        initCalendar: function() {
            const self = this;
            $("#antigravity-datepicker").flatpickr({
                inline: true,
                minDate: "today",
                onChange: function(selectedDates, dateStr, instance) {
                    self.fetchAvailability(dateStr);
                }
            });
        },

        fetchAvailability: function(dateStr) {
            const self = this;
            this.$calendarContainer.hide();
            this.$timeSlotsContainer.show();
            this.showLoading(this.$slotsGrid, 'Checking availability...');
            this.$inputDate.val(dateStr);

            $.ajax({
                url: antigravity_booking_vars.ajax_url,
                type: 'POST',
                timeout: this.requestTimeout,
                data: {
                    action: 'antigravity_get_availability',
                    nonce: antigravity_booking_vars.nonce,
                    date: dateStr
                },
                success: function(response) {
                    if (response.success) {
                        self.renderSlots(response.data);
                    } else {
                        let errorMsg = response.data;
                        
                        // Handle rate limiting
                        if (typeof errorMsg === 'string' && errorMsg.includes('Too many requests')) {
                            errorMsg = 'Too many requests. Please wait a few minutes before trying again.';
                        }
                        
                        self.showError(self.$slotsGrid, errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = 'An error occurred. Please try again.';
                    
                    if (status === 'timeout') {
                        errorMsg = 'Request timed out. Please check your internet connection and try again.';
                    } else if (xhr.status === 403) {
                        errorMsg = 'Session expired. Please refresh the page and try again.';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Server error. Please contact support if the problem persists.';
                    }
                    
                    self.showError(self.$slotsGrid, errorMsg);
                }
            });
        },

        renderSlots: function(slots) {
            if (!slots || slots.length === 0) {
                this.$slotsGrid.html('<p>No available slots for this date.</p><br><button class="back-button" id="back-to-cal">Back to Calendar</button>');
                $('#back-to-cal').on('click', this.showCalendar.bind(this));
                return;
            }

            let html = '';
            slots.forEach(function(slot) {
                const overnightClass = slot.is_overnight ? ' is-overnight' : '';
                // Store index for easy range checking
                html += `<div class="time-slot${overnightClass}" 
                            data-start="${slot.start}" 
                            data-end="${slot.end}" 
                            data-label="${slot.label}" 
                            data-is-overnight="${slot.is_overnight || false}">
                            ${slot.label}
                         </div>`;
            });
            
            html += '</div><div style="margin-top:20px; text-align:center;"><p id="range-instruction" style="font-size:0.9em; margin-bottom:10px;">Select a start time and an end time.</p><button class="back-button" id="back-to-cal-2">Pick a different date</button></div>';

            this.$slotsGrid.html(html);
            $('#back-to-cal-2').on('click', this.showCalendar.bind(this));
            
            this.selectionState = {
                start: null,
                end: null
            };
        },

        handleSlotClick: function(e) {
            const $slot = $(e.currentTarget);
            const $allSlots = $('.time-slot');
            const index = $allSlots.index($slot);
            
            // Reset if sequence is broken or full range already selected
            if (this.selectionState.start !== null && this.selectionState.end !== null) {
                this.selectionState.start = null;
                this.selectionState.end = null;
                $allSlots.removeClass('selected in-range');
            }

            if (this.selectionState.start === null) {
                // First click: Start of range
                this.selectionState.start = index;
                $slot.addClass('selected');
                $('#range-instruction').text('Now select an end time (or click the same slot for 1 hour).');
            } else {
                // Second click: End of range
                if (index < this.selectionState.start) {
                    // User clicked earlier slot, restart selection
                    this.selectionState.start = index;
                    this.selectionState.end = null;
                    $allSlots.removeClass('selected in-range');
                    $slot.addClass('selected');
                    $('#range-instruction').text('Now select an end time.');
                } else {
                    // Valid range selection
                    this.selectionState.end = index;
                    
                    // Validate Range (Check for gaps if we had blocked slots, though renderSlots usually only shows available. 
                    // But if there are gaps in *time* due to booked slots not being rendered, we need to be careful.
                    // Ideally we render booked slots as disabled so we can detect gaps. 
                    // For now, let's assume if slots are adjacent in the grid, they are continuous in time? 
                    // No, that's risky. "10:00" then "12:00" might appear adjacent if "11:00" is booked/hidden.
                    // We must check continuity.
                    
                    const $selectedRange = $allSlots.slice(this.selectionState.start, this.selectionState.end + 1);
                    
                    // Check continuity
                    let valid = true;
                    for (let i = 0; i < $selectedRange.length - 1; i++) {
                        const currentEnd = $($selectedRange[i]).data('end'); // e.g. "10:00"
                        const nextStart = $($selectedRange[i+1]).data('start'); // e.g. "10:00"
                        
                        // Simple check: currentEnd should equal nextStart? 
                        // Slot end is usually "Start + 1hr".
                        // Wait, my API returns 'end' as '11:00' for a '10:00' start.
                        // So yes, currentEnd == nextStart.
                        if (currentEnd !== nextStart) {
                            valid = false;
                            break;
                        }
                    }

                    if (!valid) {
                         alert("You cannot select a range with booked slots in between. Please select a continuous block.");
                         // Reset
                         this.selectionState.start = null;
                         this.selectionState.end = null;
                         $allSlots.removeClass('selected in-range');
                         $('#range-instruction').text('Select a start time.');
                         return;
                    }

                    // Visualize
                    $selectedRange.addClass('in-range');
                    $slot.addClass('selected'); // End is also selected
                    
                    // Process Selection
                    const startSlot = $($selectedRange[0]);
                    const endSlot = $($selectedRange[$selectedRange.length - 1]);
                    
                    const startTime = startSlot.data('start');
                    const endTime = endSlot.data('end'); // Use the end of the last slot
                    
                    // Calculate Total Hours
                    // Count slots? (Assuming 1hr each)
                    // Overnight exception: Overnight slot is 1 "slot" visually but 12 hours cost? 
                    // If range includes overnight, it should handle it.
                    // If overnight is selected, it's usually the last slot.
                    
                    let totalCost = 0;
                    $selectedRange.each((i, el) => {
                        const isOvernight = $(el).data('is-overnight');
                        if (isOvernight) {
                            totalCost += 12 * parseFloat(this.settings.hourly_rate);
                        } else {
                             // Assuming 1hr slots for now
                             // We should really calculate from start/end of each slot?
                             // Simplified: 1 slot = 1 hour rate.
                            totalCost += 1 * parseFloat(this.settings.hourly_rate);
                        }
                    });

                    this.$inputStartTime.val(startTime);
                    this.$inputEndTime.val(endTime); // Pass explicit end time
                    
                    this.$summaryDate.text(this.$inputDate.val());
                    this.$summaryTime.text(startTime + ' - ' + endTime);
                    this.$summaryCost.text(totalCost.toFixed(2));
                    
                    // Show Note if overnight is involved
                    const hasOvernight = endSlot.data('is-overnight');
                     if (hasOvernight) {
                        this.$overnightNote.show();
                    } else {
                        this.$overnightNote.hide();
                    }

                    // Auto-advance to form after short delay or immediate?
                    // User might want to adjust. Let's wait for user? 
                    // Or standard flow: Range Selected -> Show Form?
                    // Let's fade in form.
                    this.showBookingForm();
                }
            }
        },

        handleFormSubmit: function(e) {
            e.preventDefault();
            const self = this;
            const $btn = this.$form.find('.submit-button');
            const originalText = $btn.text();
            
            // Validate form before submission
            const errors = this.validateForm();
            if (errors.length > 0) {
                const errorHtml = `
                    <div class="antigravity-error">
                        <p>Please fix the following errors:</p>
                        <ul>${errors.map(err => `<li>${err}</li>`).join('')}</ul>
                    </div>
                `;
                this.$message.html(errorHtml);
                return;
            }
            
            $btn.text('Booking...').prop('disabled', true);
            this.$message.html('');

            const formData = this.$form.serialize();

            $.ajax({
                url: antigravity_booking_vars.ajax_url,
                type: 'POST',
                timeout: this.requestTimeout,
                data: formData + '&nonce=' + antigravity_booking_vars.nonce,
                success: function(response) {
                    if (response.success) {
                        if (response.data.redirect_url) {
                            // Show success message before redirect
                            self.showSuccess(self.$message, response.data.message);
                            setTimeout(() => {
                                window.location.href = response.data.redirect_url;
                            }, 2000);
                        } else {
                            self.$bookingForm.hide();
                            self.showSuccess(self.$message, response.data.message);
                            
                            // Reset form after 3 seconds
                            setTimeout(() => {
                                self.resetForm();
                            }, 3000);
                        }
                    } else {
                        let errorMsg = response.data;
                        
                        // Handle rate limiting
                        if (typeof errorMsg === 'string' && errorMsg.includes('Too many requests')) {
                            errorMsg = 'Too many requests. Please wait a few minutes before trying again.';
                        }
                        
                        self.$message.html('<div class="antigravity-error">' + errorMsg + '</div>');
                        $btn.text(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = 'Server error. Please try again.';
                    
                    if (status === 'timeout') {
                        errorMsg = 'Request timed out. Please check your internet connection and try again.';
                    } else if (xhr.status === 403) {
                        errorMsg = 'Session expired. Please refresh the page and try again.';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Server error. Please contact support if the problem persists.';
                    }
                    
                    self.$message.html('<div class="antigravity-error">' + errorMsg + '</div>');
                    $btn.text(originalText).prop('disabled', false);
                }
            });
        },
        
        /**
         * Reset form to initial state
         */
        resetForm: function() {
            this.$form[0].reset();
            this.$message.html('');
            this.$bookingForm.hide();
            this.$timeSlotsContainer.hide();
            this.$calendarContainer.slideDown();
            
            // Reset selection state
            this.selectionState = { start: null, end: null };
            $('.time-slot').removeClass('selected in-range');
        },

        showCalendar: function() {
            this.$bookingForm.hide();
            this.$timeSlotsContainer.hide();
            this.$message.empty();
            this.$calendarContainer.slideDown();
        },
        
        showSlots: function() {
            this.$bookingForm.hide();
            this.$calendarContainer.hide();
            this.$message.empty();
            this.$timeSlotsContainer.fadeIn();
        },
        
        showBookingForm: function() {
            this.$timeSlotsContainer.hide();
            this.$bookingForm.fadeIn();
        }
    };

    app.init();
});
