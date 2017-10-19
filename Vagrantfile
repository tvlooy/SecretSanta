# -*- mode: ruby -*-
# vi: set ft=ruby :

$bootstrap_script = <<SCRIPT
if [ ! -f /usr/bin/python ]; then
    PKG_PATH=http://ftp.eu.openbsd.org/pub/OpenBSD/6.2/packages/amd64/
    pkg_add python%2.7
    ln -sf /usr/local/bin/python2.7 /usr/bin/python
fi
SCRIPT

Vagrant.configure(2) do |config|

    config.vm.define :secretsanta do |secretsanta_config|

        secretsanta_config.vm.box = "tvlooy/openbsd-6.2-amd64"

        secretsanta_config.vm.provider "virtualbox" do |v|
            # show a display for easy debugging
            v.gui = false

            # RAM size
            v.memory = 2048

            # Allow symlinks on the shared folder
            v.customize ["setextradata", :id, "VBoxInternal2/SharedFoldersEnableSymlinksCreate/v-root", "1"]
        end

        secretsanta_config.vm.synced_folder ".", "/var/www/htdocs/vagrant", type: "nfs"
        secretsanta_config.vm.network "private_network", ip: "192.168.33.50"

        # Shell provisioning
        #secretsanta_config.vm.provision :shell, :path => "shell_provisioner/run.sh"

        secretsanta_config.vm.provision "shell", inline: $bootstrap_script
        secretsanta_config.vm.provision "ansible" do |ansible|
            #ansible.verbose = "v"
            ansible.playbook = "provisioning/dev/playbook.yml"
        end

    end

end

