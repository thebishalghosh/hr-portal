/**
 * Task Progress Management
 * Handles AJAX updates for task progress and link submissions
 */
class TaskProgressManager {
    constructor() {
        this.initEventListeners();
    }
    
    initEventListeners() {
        // Add link button
        const addLinkBtn = document.getElementById('add-link');
        if (addLinkBtn) {
            addLinkBtn.addEventListener('click', this.addLinkField.bind(this));
        }
        
        // Remove link buttons
        document.querySelectorAll('.remove-link').forEach(button => {
            button.addEventListener('click', this.removeLinkField.bind(this));
        });
        
        // Task progress form
        const progressForm = document.getElementById('task-progress-form');
        if (progressForm) {
            progressForm.addEventListener('submit', this.handleProgressSubmit.bind(this));
        }
    }
    
    addLinkField(event) {
        const linksContainer = document.getElementById('links-container');
        const newRow = document.createElement('div');
        newRow.className = 'row mb-2 link-row';
        newRow.innerHTML = `
            <div class="col-md-6">
                <input type="url" class="form-control" name="links[]" placeholder="https://example.com">
            </div>
            <div class="col-md-5">
                <input type="text" class="form-control" name="link_descriptions[]" placeholder="Link description">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger remove-link"><i class="fas fa-times"></i></button>
            </div>
        `;
        linksContainer.appendChild(newRow);
        
        // Add event listener to the new remove button
        newRow.querySelector('.remove-link').addEventListener('click', this.removeLinkField.bind(this));
    }
    
    removeLinkField(event) {
        const row = event.target.closest('.link-row');
        if (row) {
            row.parentNode.removeChild(row);
        }
    }
    
    async handleProgressSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const taskId = form.dataset.taskId;
        const status = form.querySelector('[name="status"]').value;
        const description = form.querySelector('[name="description"]').value;
        
        // Collect links
        const links = [];
        const linkInputs = form.querySelectorAll('[name="links[]"]');
        const linkDescriptions = form.querySelectorAll('[name="link_descriptions[]"]');
        
        for (let i = 0; i < linkInputs.length; i++) {
            if (linkInputs[i].value.trim() !== '') {
                links.push({
                    url: linkInputs[i].value.trim(),
                    description: linkDescriptions[i] ? linkDescriptions[i].value.trim() : ''
                });
            }
        }
        
        // Show loading state
        const submitBtn = form.querySelector('[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...';
        
        // Log the request data for debugging
        console.log('Sending data:', {
            task_id: taskId,
            status,
            description,
            links
        });
        
        try {
            // Use the correct API endpoint path
            const response = await fetch('../api/update_task_progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    task_id: taskId,
                    status,
                    description,
                    links
                })
            });
            
            // Log response details for debugging
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Get the raw response text
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Error parsing JSON response:', e);
                throw new Error('Invalid response from server. Check the console for details.');
            }
            
            if (data.success) {
                // Show success message
                this.showAlert('success', data.message);
                
                // Refresh the page after a short delay
                setTimeout(() => {
                    window.location.href = `task_details.php?task_id=${taskId}&updated=1`;
                }, 1500);
            } else {
                this.showAlert('danger', data.message || 'An error occurred while updating task progress');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        } catch (error) {
            console.error('Error updating task progress:', error);
            this.showAlert('danger', 'Error: ' + error.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }
    showAlert(type, message) {
        const alertContainer = document.getElementById('alert-container');
        if (!alertContainer) return;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        alertContainer.innerHTML = '';
        alertContainer.appendChild(alert);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.classList.remove('show');
                setTimeout(() => {
                    if (alert.parentNode) {
                        alertContainer.removeChild(alert);
                    }
                }, 150);
            }
        }, 5000);
    }
}

// Initialize the task progress manager when the DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new TaskProgressManager();
});