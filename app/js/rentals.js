/**
 * File: rentals.js
 * Path: /public/assets/js/rentals.js
 * Purpose: JavaScript for rental operations (confirm, activate, complete, cancel, payments, extensions)
 * Phase: Phase 7 - Rental System
 * Created: 2025-10-24
 */

// Confirm Rental
async function confirmRental(id) {
    if (!confirm(translate('rental.confirm_action'))) {
        return;
    }
    
    try {
        const response = await fetch(`/admin/rentals/confirm/${id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast(translate('error.server_error'), 'error');
    }
}

// Activate Rental
async function activateRental(id) {
    const modal = createModal('activate_rental_modal', translate('rental.activate'), `
        <form id="activateForm">
            <div class="form-group">
                <label>${translate('rental.odometer_start')} <span class="required">*</span></label>
                <input type="number" name="odometer_start" class="form-control" required>
            </div>
            <div class="form-group">
                <label>${translate('rental.fuel_level_start')} <span class="required">*</span></label>
                <select name="fuel_level_start" class="form-control" required>
                    <option value="empty">${translate('rental.fuel.empty')}</option>
                    <option value="quarter">${translate('rental.fuel.quarter')}</option>
                    <option value="half">${translate('rental.fuel.half')}</option>
                    <option value="three_quarters">${translate('rental.fuel.three_quarters')}</option>
                    <option value="full" selected>${translate('rental.fuel.full')}</option>
                </select>
            </div>
            <div class="form-group">
                <label>${translate('rental.car_condition_start')}</label>
                <textarea name="car_condition_start" class="form-control" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">${translate('common.submit')}</button>
        </form>
    `);
    
    modal.show();
    
    document.getElementById('activateForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch(`/admin/rentals/activate/${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': getCsrfToken()
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(data.message, 'success');
                modal.hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            showToast(translate('error.server_error'), 'error');
        }
    });
}

// Complete Rental
async function completeRental(id) {
    const modal = createModal('complete_rental_modal', translate('rental.complete'), `
        <form id="completeForm">
            <div class="form-group">
                <label>${translate('rental.odometer_end')} <span class="required">*</span></label>
                <input type="number" name="odometer_end" class="form-control" required>
            </div>
            <div class="form-group">
                <label>${translate('rental.fuel_level_end')} <span class="required">*</span></label>
                <select name="fuel_level_end" class="form-control" required>
                    <option value="empty">${translate('rental.fuel.empty')}</option>
                    <option value="quarter">${translate('rental.fuel.quarter')}</option>
                    <option value="half">${translate('rental.fuel.half')}</option>
                    <option value="three_quarters">${translate('rental.fuel.three_quarters')}</option>
                    <option value="full">${translate('rental.fuel.full')}</option>
                </select>
            </div>
            <div class="form-group">
                <label>${translate('rental.car_condition_end')}</label>
                <textarea name="car_condition_end" class="form-control" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-success">${translate('common.submit')}</button>
        </form>
    `);
    
    modal.show();
    
    document.getElementById('completeForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch(`/admin/rentals/complete/${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': getCsrfToken()
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(data.message, 'success');
                modal.hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            showToast(translate('error.server_error'), 'error');
        }
    });
}

// Cancel Rental
async function cancelRental(id) {
    const reason = prompt(translate('rental.cancellation_reason_prompt'));
    
    if (!reason) {
        return;
    }
    
    try {
        const response = await fetch(`/admin/rentals/cancel/${id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({ cancellation_reason: reason })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast(translate('error.server_error'), 'error');
    }
}

// Add Payment
async function addPayment(rentalId) {
    const modal = createModal('add_payment_modal', translate('rental.add_payment'), `
        <form id="paymentForm">
            <div class="form-group">
                <label>${translate('rental.amount')} <span class="required">*</span></label>
                <input type="number" name="amount" class="form-control" step="0.01" required>
            </div>
            <div class="form-group">
                <label>${translate('rental.payment_type')} <span class="required">*</span></label>
                <select name="payment_type" class="form-control" required>
                    <option value="rental">${translate('rental.payment_type.rental')}</option>
                    <option value="deposit">${translate('rental.payment_type.deposit')}</option>
                    <option value="fine">${translate('rental.payment_type.fine')}</option>
                    <option value="extra">${translate('rental.payment_type.extra')}</option>
                </select>
            </div>
            <div class="form-group">
                <label>${translate('rental.payment_date')} <span class="required">*</span></label>
                <input type="datetime-local" name="payment_date" class="form-control" value="${new Date().toISOString().slice(0, 16)}" required>
            </div>
            <div class="form-group">
                <label>${translate('rental.notes')}</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">${translate('common.save')}</button>
        </form>
    `);
    
    modal.show();
    
    document.getElementById('paymentForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch(`/admin/rentals/${rentalId}/payment/add`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': getCsrfToken()
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(data.message, 'success');
                modal.hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            showToast(translate('error.server_error'), 'error');
        }
    });
}

// Delete Payment
async function deletePayment(paymentId) {
    if (!confirm(translate('rental.confirm_delete_payment'))) {
        return;
    }
    
    try {
        const response = await fetch(`/admin/rentals/payment/${paymentId}/delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast(translate('error.server_error'), 'error');
    }
}

// Extend Rental
async function extendRental(rentalId) {
    const modal = createModal('extend_rental_modal', translate('rental.extend'), `
        <form id="extendForm">
            <div class="form-group">
                <label>${translate('rental.new_end_date')} <span class="required">*</span></label>
                <input type="datetime-local" name="new_end_date" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-warning">${translate('common.submit')}</button>
        </form>
    `);
    
    modal.show();
    
    document.getElementById('extendForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch(`/admin/rentals/${rentalId}/extend`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': getCsrfToken()
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(data.message, 'success');
                modal.hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            showToast(translate('error.server_error'), 'error');
        }
    });
}

// Helper: Create Modal
function createModal(id, title, content) {
    let modal = document.getElementById(id);
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = id;
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">${content}</div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    } else {
        modal.querySelector('.modal-title').textContent = title;
        modal.querySelector('.modal-body').innerHTML = content;
    }
    
    return {
        show: () => $(modal).modal('show'),
        hide: () => $(modal).modal('hide')
    };
}
