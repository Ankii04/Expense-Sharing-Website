function deleteGroup(groupId) {
    if (confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
        // Create and submit a form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete-group.php';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'group_id';
        input.value = groupId;

        form.appendChild(input);
        document.body.appendChild(form);

        // Show loading state on button
        const deleteBtn = document.querySelector('.delete-group-btn');
        const spinner = deleteBtn.querySelector('.spinner');
        const text = deleteBtn.querySelector('.text');
        deleteBtn.disabled = true;
        spinner.classList.remove('d-none');
        text.classList.add('d-none');

        // Submit the form
        form.submit();
    }
}
