;( function() {
	
	"use strict";

	App.Views.FormView = Backbone.View.extend({

		el: "#form-view",
		events: {
			"input input[name=chart-name]": "onNameChange",
			"click .remove-uploaded-file-btn": "onRemoveUploadedFile",
			"change .countries-select": "onCountriesSelect",
			"submit form": "onFormSubmit",
		},

		initialize: function( options ) {
			
			this.dispatcher = options.dispatcher;
			
			//create related models
			App.ChartVariablesCollection = new App.Collections.ChartVariablesCollection();
			App.AvailableEntitiesCollection = new App.Collections.AvailableEntitiesCollection();
			App.ChartDimensionsModel = new App.Models.ChartDimensionsModel();
			App.AvailableTimeModel = new App.Models.AvailableTimeModel();

			//create subviews
			this.basicTabView = new App.Views.Form.BasicTabView( { dispatcher: this.dispatcher } );
			this.axisTabView = new App.Views.Form.AxisTabView( { dispatcher: this.dispatcher } );
			this.descriptionTabView = new App.Views.Form.DescriptionTabView( { dispatcher: this.dispatcher } );
			this.stylingTabView = new App.Views.Form.StylingTabView( { dispatcher: this.dispatcher } );
			this.exportTabView = new App.Views.Form.ExportTabView( { dispatcher: this.dispatcher } );

			//fetch doms
			this.$removeUploadedFileBtn = this.$el.find( ".remove-uploaded-file-btn" );
			this.$filePicker = this.$el.find( ".file-picker-wrapper [type=file]" );
			this.$selectedCountriesBox = this.$el.find( ".selected-countries-box" );

			//setup events
			var that = this;
			CSV.begin( this.$filePicker.selector ).go( 
				function( err, data ) {
					that.onCsvSelected( err, data );
				}
			);
			App.ChartModel.on( "change:selected-countries", this.render, this );

			this.$removeUploadedFileBtn.hide();

			this.render();

		},

		render: function() {

			this.updateSelectedCountries();

		},

		onNameChange: function( evt ) {

			var $input = $( evt.target );
			App.ChartModel.set( "chart-name", $input.val() );

		},

		onCsvSelected: function( err, data ) {

			if( err ) {
				console.error( err );
				return;
			}

			this.$removeUploadedFileBtn.show();

			if( data && data.rows ) {
				var mappedData = App.Utils.mapData( data.rows );
				App.ChartModel.set( "chart-data", mappedData );
			}

		},

		onCountriesSelect: function( evt ) {

			var $select = $( evt.target );
			App.ChartModel.addSelectedCountry( $select.val() );

		},

		onRemoveUploadedFile: function( evt ) {

			this.$filePicker.replaceWith( this.$filePicker.clone() );
			//refetch dom
			this.$filePicker = this.$el.find( ".file-picker-wrapper [type=file]" );
			this.$filePicker.prop( "disabled", false);

			var that = this;
			CSV.begin( this.$filePicker.selector ).go( function( err, data ) {
					that.onCsvSelected( err, data );
			} );

			this.$removeUploadedFileBtn.hide();

		},

		updateSelectedCountries: function() {

			//remove everything
			this.$selectedCountriesBox.empty();

			var that = this,
				selectedCountries = App.ChartModel.get( "selected-countries" );

			$.each( selectedCountries, function( i, v ) {
				that.$selectedCountriesBox.append( "<li class='country-label' data-name='" + v + "'>" + v + "<span class='fa fa-remove'></span></li>" );
			} );

			var $lis = this.$selectedCountriesBox.find( ".country-label" ),
				$lisRemoveBtns = $lis.find( ".fa-remove" ),
				colorPicker = null;

			$lis.on( "click", function( evt ) {

				evt.preventDefault();

				var $countryLabel = $( evt.currentTarget );
				if( colorPicker ) {
					colorPicker.close();
				}
				colorPicker = new App.Views.UI.ColorPicker( $countryLabel );
				colorPicker.init( $countryLabel );
				colorPicker.onSelected = function( value ) {
					$countryLabel.css( "background-color", value );
					//$el.parent().parent().attr( "data-color", value );
					colorPicker.close();
					//that.$el.trigger( "change" );
				};

			} );	

			$lisRemoveBtns.on( "click", function( evt ) {

				evt.preventDefault();
				var $this = $( this ),
					$parent = $this.parent(),
					countryName = $parent.attr( "data-name" );
				App.ChartModel.removeSelectedCountry( countryName );

			})	

		},

		onFormSubmit: function( evt ) {
			
			$.ajaxSetup( {
				headers: { 'X-CSRF-TOKEN': $('[name="_token"]').val() }
			} );

			evt.preventDefault();

			var dispatcher = this.dispatcher;
			App.ChartModel.save( {}, {
				success: function ( model, response, options ) {
					alert( "The chart saved succesfully" );
					dispatcher.trigger( "chart-saved", response.data.id, response.data.viewUrl );
				},
				error: function (model, xhr, options) {
					console.error("Something went wrong while saving the model", xhr );
					alert( "Opps, there was a problem saving your chart." );
				}
			});

		}

	});

})();