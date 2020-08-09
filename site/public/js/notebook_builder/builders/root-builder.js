class RootBuilder extends Builder {
    constructor(attachment_div) {
        super(attachment_div);

        this.form_options = new FormOptionsWidget();
        attachment_div.appendChild(this.form_options.render());

        this.load();
    }
}