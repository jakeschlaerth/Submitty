class ImageWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'image',
        };
    }

    render() {
        const container = this.getContainer('Image');
        container.classList.add('image-widget');

        // Setup interactive area
        const interactive_area = container.querySelector('.interactive-container');
        interactive_area.innerHTML = this.getImageTemplate(this.state.height, this.state.width, this.state.alt_text);
        this.imageSelectedAction(interactive_area);

        const remove_button = container.querySelector('input[type=button][value="Remove"]');
        remove_button.addEventListener('click', event => {
            if (this.state.image) {
                syncFile(null, this.state.image, builder_data.g_id, 'input', 'delete');
            }
        });

        this.dom_pointer = container;
        return container;
    }

    commitState() {
        const height_input = this.dom_pointer.querySelector('.height-input');
        height_input.value ? this.state.height = parseInt(height_input.value) : delete this.state.height;

        const width_input = this.dom_pointer.querySelector('.width-input');
        width_input.value ? this.state.width = parseInt(width_input.value) : delete this.state.width;

        const alt_text_input = this.dom_pointer.querySelector('.alt-text-input');
        alt_text_input.value ? this.state.alt_text = alt_text_input.value : delete this.state.alt_text;
    }

    getJSON() {
        this.commitState();

        if (this.state.image) {
            return this.state;
        }
    }

    load(data) {
        this.state = data;
    }

    getImageTemplate(height, width, alt_text) {
        return `
        <div class="image-container"></div>
        <input type="file" accept="image/*">
        <div class="image-options">
            <div class="image-col-small">
                <label>
                    Height:
                    <input class="height-input" type="number" placeholder="Default" min="1" value="${height ? height : ''}">
                </label>
            </div>
            <div class="image-col-small">
                <label>
                    Width:
                    <input class="width-input" type="number" placeholder="Default" min="1" value="${width ? width : ''}">
                </label>
            </div>
            <div class="image-col-large">
                <label>
                    Alternate Text:
                    <textarea class="alt-text-input" placeholder="For accessibility, provide a short description of this image's contents.">${alt_text ? alt_text : ''}</textarea>
                </label>
            </div>
        </div>`
    }

    imageSelectedAction(interactive_area) {
        const reader = new FileReader();
        const file_selector = interactive_area.querySelector('input[type=file]');
        const image_container = interactive_area.querySelector('.image-container');

        reader.onload = event => {
            const image = document.createElement('img');
            image.src = event.target.result;

            image_container.innerHTML = '';
            image_container.appendChild(image);
        }

        file_selector.addEventListener('change', event => {
            const file = event.target.files[0];

            if (file) {
                syncFile(file, file.name, builder_data.g_id, 'input', 'upload');
                this.state.image = file.name;
                reader.readAsDataURL(file);
                file_selector.style.display = 'none';
            }
        });
    }
}
