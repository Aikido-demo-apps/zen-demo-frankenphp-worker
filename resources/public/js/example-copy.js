document.addEventListener('DOMContentLoaded', function() {
    // Find all elements with 'example' class
    const exampleElements = document.getElementsByClassName('example');
    // Find the input element with 'example-input' class
    const inputElement = document.querySelector('.example-input');

    // Convert HTMLCollection to Array and add click event to each element
    Array.from(exampleElements).forEach(function(element) {
        element.addEventListener('click', function() {
            // Copy the content of clicked element to input field
            if (inputElement) {
                inputElement.value = this.textContent.trim();

                // Optional: Visual feedback that copying worked
                this.style.backgroundColor = '#ffeb3b';
                setTimeout(() => {
                    this.style.backgroundColor = '';
                }, 200);
            }
        });

        // Optional: Add cursor pointer to show it's clickable
        element.style.cursor = 'pointer';
    });
});
