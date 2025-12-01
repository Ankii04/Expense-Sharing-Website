document.addEventListener('DOMContentLoaded', function() {
    const expenseForm = document.getElementById('addExpenseForm');
    if (!expenseForm) return;

    const submitButton = document.getElementById('addExpenseBtn');
    const loadingText = submitButton.querySelector('.loading-text');
    const normalText = submitButton.querySelector('.normal-text');

    expenseForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Show loading state
        submitButton.disabled = true;
        loadingText.classList.remove('d-none');
        normalText.classList.add('d-none');

        try {
            // Get form data
            const formData = new FormData(expenseForm);

            // Add group_id from URL
            const urlParams = new URLSearchParams(window.location.search);
            formData.append('group_id', urlParams.get('id'));

            // Submit form via AJAX
            const response = await fetch('add-expense.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Reset form
                expenseForm.reset();
                
                // Show success message
                const alert = document.createElement('div');
                alert.className = 'alert alert-success';
                alert.textContent = 'Expense added successfully!';
                expenseForm.insertBefore(alert, expenseForm.firstChild);

                // Refresh page after a short delay
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(data.message || 'Failed to add expense');
            }
        } catch (error) {
            console.error('Error:', error);
            alert(error.message || 'An error occurred while adding the expense');
        } finally {
            // Reset button state
            submitButton.disabled = false;
            loadingText.classList.add('d-none');
            normalText.classList.remove('d-none');
        }
    });
});
