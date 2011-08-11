<?php
/*
	Author: Mark Maunder <mmaunder@gmail.com>
	Author website: http://markmaunder.com/
	License: GPL 3.0
*/
?>

<div class="wrap">
	<h2 class="depmintHead">DeployMint Documentation</h2>
	<h3>Definitions</h3>
	<ul>
		<li>snapshot: A copy in time of a single Wordpress blog that can be deployed to another blog (or back to itself)</li>
		<li>project: A group of blogs from which you take snapshots and to which you deploy snapshots.</li>
		<li>git: The version control system that is used to store snapshots. Find out more <a href="http://git-scm.com/">here</a>.</li>
		<li>mysqldump: The mysql utility we use to extract each table in your blog and store it on disk before it is added to 'git' for storage.</li>
		<li>deployment: When you write a snapshot to a blog and make it look identical to the blog from where the snapshot was taken.</li>
	</ul>

	<h3>Why you need DeployMint</h3>
	<p>
		<b>When using Wordprses as a CMS you don't usually publish individual blog entries.</b> Instead you will create an entire website of pages in a testing or staging environment. Once you're ready you will want them all published at once. 
		You would also like the ability to archive old versions of your site. You also want the ability to easily back-out any changes you make in case there's a problem.
		And it would be nice to have an emergency safety mechanism in case all this snapshotting and deploying goes awry. You also have limited disk space so you want the system to only store 
		the changes between each snapshot. And it should be insanely fast with zero down-time when you deploy.
	</p>
	<p>
		DeployMint (SD) provides all this functionality. 
		When you take a snapshot of a blog with SD, it stores it in GIT which is a very robust, fast and storage efficient repository.
		When you deploy a new blog, SD prepares the entire blog in a separate database and uses MySQL's RENAME function to switch the old
		blog out and the new blog into place. This locks the database for microseconds and any pending queries to the site are queued up until the process is complete.
		The effect is that your visitors will not notice anything besides a new version of your site appearing. 
	</p>
	<p>
		DeployMint (SD) for Wordpress uses the following workflow:
		<ol>
			<li>Create a new project. You'll see the name of your project appear in the DeployMint menu.</li>
			<li>Add blogs to your project. You will take snapshots of blogs in this group and will deploy snapshots to blogs in this group.</li>
			<li>Go to the project menu and take your first snapshot of a blog. We recommend you snapshot all blogs in the project before you start in case you need to back-out a deployment.</li>
			<li>On the same page, select the snapshot you took and select a blog to deploy it to. Hit the deploy button. The blog you deployed to should now mirror the blog you snapshotted in everything except it's hostname.</li>
			<li>If you run into trouble, go to the "Deployment Undo Log" page and you'll see a list of full Wordpress backups that we made every time you deployed a new snapshot. You can revert your entire Wordpress installation (all blogs in your network) to a backup we made before you deployed a snapshot.</li>
		</ol>
	</p>
	<h3>The concept of a Project</h3>
	<p>
		A project usually has at least two blogs in it. One blog will be your staging blog where you preview your website. This blog will be protected and not visible by the public. 
		You can use the "Registered users only" plugin if you want a blog to only be accessible by registered users.
	</p>
	<p>
		Your second blog will be your "Live" blog. You will make changes to your staging blog and once you're done, you will use SD to take a snapshot of your staging blog.
		Then you will deploy that snapshot to your "Live" blog. 
	</p>
	<p>
		Technically you can take a snapshot of "live" and deploy it to "staging" but you usually won't do that. One scenario where you may need
		to deploy from live to staging is if you mess up your staging environment so badly that you simply want to revert it to the way the live blog looks.
	</p>
		

	<h3>How DeployMint stores snapshots</h3>
	<p>
		DeployMint uses <a href="http://git-scm.com/">git</a> on the back-end to store it's snapshots.
		Each SD project is a separate git repository. 
	</p>
	<p>
		When you ask SD to make a snapshot, it uses a utility called mysqldump to make a copy to disk of each of the tables of the blog you want to snapshot.
		Those tables are stored in the project's git repository. Each time you create a new snapshot, we commit the changes in tables to the git repository and create a new branch with your snapshot name.
	</p>
	<h3>How to back out a deployment</h3>
	<p>
		As mentioned in the Workflow bullets above, we recommend you snapshot all blogs in a project before you do any deployments. 
		If you deploy a snapshot to your Live blog, you can back-out the changes by deploying the snapshot you took of Live before you tried to deploy a snapshot from Staging. 
	</p>
		
	<h3>Emergency Revert</h3>
	<p>
		Because SD manipulates your database directly, we have built in a safety mechanism. Every time you deploy a new snapshot to a blog, we back up your ENTIRE Wordpress database.
		If a deployment leaves your Wordpress installation in a broken state, you can revert to the previous state of your Wordpress installation.
		This is only to be used in an emergency. 
	</p>
	<p>
		It's important to note that we back up the entire Wordpress MU installation when you deploy to a single blog. If you have many blogs in your 
		Wordpress MU installation and you choose to restore your entire Wordpress database, you will overwrite any changes on ALL blogs in your network that have occured since the deployment. 
		Once again, this is an emergency facility that you will hopefully never have to use.
	</p>
	<p>
		Emergency Revert doesn't use git to store a copy of your Wordpress databse. Instead it simply creates a new database in MySQL and makes a copy of your Wordpress MU database there. 
		This is not as disk efficient, so you will need to periodically delete databases created under Emergency Revert. The reason we don't use git is because this system is designed to be as simple and reliable as possible.
	</p>



</div>
