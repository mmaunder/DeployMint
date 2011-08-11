/*
	Author: Mark Maunder <mmaunder@gmail.com>
	Author website: http://markmaunder.com/
	License: GPL 3.0
*/

if(! window['deploymint']){
window['deploymint'] = {
	init: function(){
		jQuery('#sdAjaxLoading').hide().ajaxStart(function(){ jQuery(this).show(); }).ajaxStop(function(){ jQuery(this).hide(); });
	},
	updateOptions: function(git, mysql, mysqldump, datadir, numBackups){
		var self = this;
		jQuery('#sdOptErrors').hide();
		jQuery('#sdOptErrors').empty();
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_updateOptions",
				git: git,
				mysql: mysql,
				mysqldump: mysqldump,
				datadir: datadir,
				numBackups: numBackups
				},
			success: function(resp){
				if(resp.errs){
					for(var i = 0; i < resp.errs.length; i++){
						console.log(resp.errs[i])
						deploymint.addOptError(resp.errs[i], true);
					}
					jQuery('#sdOptErrors').fadeIn();
					return;
				} else if(resp.ok){
					jQuery('.error').hide();
					deploymint.addOptError("Your options have been succesfully updated.");
					jQuery('#sdOptErrors').fadeIn();
				} else {
					deploymint.addOptError("An error occured updating your options.", true);
					jQuery('#sdOptErrors').fadeIn();
				}
			},
			error: function(){
				deploymint.addOptError("An error occured updating your options.", true);
				jQuery('#sdOptErrors').fadeIn();
			}
			});

	},	
	addOptError: function(err, isError){
		jQuery('#sdOptErrors').append('<div class="' + (isError ? 'error' : 'updated') + ' sdOptErrWrap"><p><strong>' + err + '</strong></p></div>');
	},
	addBlogToProject: function(projectID, blogID){
		var self = this;
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_addBlogToProject",
				projectID: projectID,
				blogID: blogID
				},
			success: function(resp){
				if(resp.err){
					alert(resp.err);
					return;
				}
				self.reloadProjects();
			},
			error: function(){}
			});

	},	
	deleteBackups: function(delArr){
		if(confirm("Are you 100% sure you want to delete the selected backups? This can't be undone.")){
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_deleteBackups",
				toDel: delArr
				},
			success: function(resp){
				if(resp.err){
					alert(resp.err);
					return;
				}
				//deploymint.reloadProjects();
				window.location.reload(false);

			},
			error: function(){}
			});
		}

	},
	deleteProject: function(projectID){
		if(confirm("Are you 100% sure you want to delete this project? This can't be undone.")){
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_deleteProject",
				projectID: projectID
				},
			success: function(resp){
				if(resp.err){
					alert(resp.err);
					return;
				}
				//deploymint.reloadProjects();
				window.location.reload(false);

			},
			error: function(){}
			});
		}

	},
	removeBlogFromProject: function(projectID, blogID){
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_removeBlogFromProject",
				projectID: projectID,
				blogID: blogID
				},
			success: function(resp){
				if(resp.err){
					alert(resp.err);
					return;
				}
				deploymint.reloadProjects();
			},
			error: function(){}
			});

	},
	createProject: function(name){
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_createProject",
				name: name
				},
			success: function(resp){
				if(resp.err){
					alert(resp.err);
					return;
				}
				//deploymint.reloadProjects();
				window.location.reload(false);
			},
			error: function(){}
			});
	},
	reloadProjects: function(){
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_reloadProjects"
				},
			success: function(resp){
				if(resp.err){
					alert(resp.err);
					return;
				}
				jQuery('#sdProjects').empty();
				jQuery('#sdProjTmpl').tmpl(resp).appendTo('#sdProjects');
			},
			error: function(){}
			});


	},
	createSnapshot: function(projectid, blogid, name, desc){
		var self = this;
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_createSnapshot",
				projectid: projectid,
				blogid: blogid,
				name: name,
				desc: desc
				},
			success: function(resp){
				if(resp.err){
					alert(resp.err);
					return;
				}
				if(! resp.ok){
					alert("An unknown error occurred taking your snapshot.");
					return;
				}
				self.updateDeploySnapshot(projectid, name);
			},
			error: function(arg1, arg2, arg3){ throw("Ajax exception caught: " + arg1);  }
			});

	},
	deploySnapshot: function(projectid, blogid, name){
		var self = this;
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_deploySnapshot",
				projectid: projectid,
				blogid: blogid,
				name: name
				},
			success: function(resp){
				if(resp.err){
					alert(resp.err);
					return;
				}
				if(resp.ok){
					alert("Deployed succesfully. The total time the database was locked was " + resp.lockTime + " seconds.");
				} else {
					alert("An unknown error occurred taking your snapshot.");
					return;
				}
			},
			error: function(arg1, arg2, arg3){ throw("Ajax exception caught: " + arg1);  }
			});

	},
	undoDeploy: function(dbname){
		var self = this;
		if(! confirm("Are you sure you want to revert your ENTIRE Wordpress installation to this backup that we took before deployment?")){
			return;
		}
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_undoDeploy",
				dbname: dbname
				},
			success: function(resp){
				if(resp.err){
					alert(resp.err);
					window.location.reload(false);

					return;
				}
				if(resp.ok){
					alert("The Wordpress installation was sucessfully reverted.");
					window.location.reload(false);
					return;
				} else {
					alert("An unknown error occurred trying to revert your wordpress installation.");
					window.location.reload(false);
					return;
				}
			},
			error: function(arg1, arg2, arg3){ throw("Ajax exception caught: " + arg1);  }
			});

	},

	updateDeploySnapshot: function(projectid, selectedSnap){
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_updateDeploySnapshot",
				projectid: projectid
				},
			success: function(resp){
				if(resp.err){
					alert(resp.err);
					return;
				}
				jQuery('#sdDeploySnapshot').empty();
				resp['selectedSnap'] = selectedSnap;
				jQuery('#sdDeploySnapTmpl').tmpl(resp).appendTo('#sdDeploySnapshot');
				deploymint.updateSnapDesc(projectid, jQuery('#sdDepSnapshot').val());
			},
			error: function(arg1, arg2, arg3){ throw("Ajax exception caught: " + arg1);  }
			});

	},
	updateSnapDesc: function(projectid, snapname){
		if(! snapname){ return; }
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_updateSnapDesc",
				projectid: projectid,
				snapname: snapname
				},
			success: function(resp){
				if(resp.err){
					alert(resp.err);
					return;
				}
				jQuery('#sdSnapDesc2').empty();
				jQuery('#sdSnapDesc2').html(resp.desc); 
			},
			error: function(arg1, arg2, arg3){ throw("Ajax exception caught: " + arg1);  }
			});

	},


	updateCreateSnapshot: function(projectid){
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_updateCreateSnapshot",
				projectid: projectid
				},
			success: function(resp){
				if(resp.err){
					alert(resp.err);
					return;
				}
				jQuery('#sdCreateSnapshot').empty();
				jQuery('#sdCreateSnapTmpl').tmpl(resp).appendTo('#sdCreateSnapshot');
			},
			error: function(arg1, arg2, arg3){ throw("Ajax exception caught: " + arg1);  }
			});

	},
	deploy: function(){
		jQuery.ajax({
			type: "POST",
			url: DeployMintVars.ajaxURL,
			dataType: "json",
			data: {
				action: "deploymint_deploy",
				deployFrom: jQuery('#deploymintFrom').val(),
				deployTo: jQuery('#deploymintTo').val()
				},
			success: function(resp){
				jQuery('#deploymintnotice1').empty().hide();
				jQuery('#deployminttmpl1').tmpl(resp).appendTo('#deploymintnotice1');
				jQuery('#deploymintnotice1').fadeIn();
			},
			error: function(xhr, ajo, err){
			}
		});
	},
	deployFinal: function(fromid, toid, msg){

	}

};
}
jQuery(document).ready(function(){
	deploymint.init();
	});
