<?php
/*
	Author: Mark Maunder <mmaunder@gmail.com>
	Author website: http://markmaunder.com/
	License: GPL 3.0
*/
?>
<div id="sdAjaxLoading" style="display: none; position: fixed; right: 1px; top: 1px; width: 100px; background-color: #F00; color: #FFF; font-size: 12px; font-family: Verdana, arial; font-weight: normal; text-align: center; z-index: 100; border: 1px solid #CCC;">Loading...</div>
<div class="wrap">
	<h2 class="depmintHead">Manage Projects</h2>
	<table class="form-table deploymintTable">
	<tr>
		<td>Enter the name of a project to create:</td>
		<td><input type="text" id="sdProjectName" value="" size="15" maxlength="15" /></td>

		<td><input type="button" name="but2" value="Create a new project" onclick="deploymint.createProject(jQuery('#sdProjectName').val()); return false;" class="button-primary" /></td>
	</tr>
	</table>
	</p>
	<p id="sdProjects">
	</p>

		
</div>
<script type="text/x-jquery-tmpl" id="sdProjTmpl">
<div id="sdProj${id}">
{{each(i,proj) projects}}
<h2>Project: ${proj.name}&nbsp;<a href="#" onclick="deploymint.deleteProject(${proj.id}); return false;" style="font-size: 10px;">remove</a></h2>
<div class="depProjWrap">
	Add a blog to this project:&nbsp;<select id="projAddSel${proj.id}">
	{{if proj.numNonmembers}}
	{{each(k,blog) proj.nonmemberBlogs}}
	<option value="${blog.blog_id}">${blog.domain}</option>
	{{/each}}
	{{else}}
	<option value="">--No blogs left to add--</option>
	{{/if}}
	</select>&nbsp;<input type="button" name="but12" value="Add this blog to the project" onclick="deploymint.addBlogToProject(${proj.id}, jQuery('#projAddSel${proj.id}').val()); return false;" />
	<h3 class="depSmallHead">Blogs that are part of this project:</h3>
	{{if proj.memberBlogs.length}}
	<ul class="depList">
		{{each(l,blog) proj.memberBlogs}}
		<li>${blog.domain}&nbsp;<a href="#" onclick="deploymint.removeBlogFromProject(${proj.id}, ${blog.blog_id}); return false;" style="font-size: 10px;">remove</a></li>
		{{/each}}
	</ul>
	{{else}}
	<i>&nbsp;&nbsp;You have not added any blogs to this project yet.</i>
	{{/if}}
</div>
{{/each}}

</div>
</script>
<script type="text/javascript">
jQuery(function(){
	deploymint.reloadProjects();
	});
</script>
